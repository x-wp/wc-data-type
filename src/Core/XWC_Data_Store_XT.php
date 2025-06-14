<?php //phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnsupportedIdentifierPlaceholder
/**
 * Extended_Data_Store class file
 *
 * @package    WooCommerce Utils
 * @subpackage Data
 */

use XWC\Data\Entity;
use XWC\Data\Repo;

/**
 * Extended data store for searching and getting data from the database.
 *
 * @template T of XWC_Data
 */
class XWC_Data_Store_XT extends WC_Data_Store_WP implements WC_Object_Data_Store_Interface {
    /**
     * Meta handler trait.
     *
     * @use Repo\Meta_Handler<T>
     */
    use Repo\Meta_Handler;

    /**
     * Term handler trait.
     *
     * @use Repo\Term_Handler<T>
     */
    use Repo\Term_Handler;

    /**
     * Lookup handler trait.
     *
     * @use Repo\Lookup_Handler<T>
     */
    use Repo\Lookup_Handler;

    /**
     * Query handler trait.
     *
     * @use Repo\Query_Handler<T>
     */
    use Repo\Query_Handler;

    protected string $object_type;

    /**
     * Object arguments.
     *
     * @var array{
     *   core_data: array<string,mixed>,
     *   data: array<string,mixed>,
     *   tax_data: array<string,mixed>,
     *   prop_types: array<string,'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'string'|'other'>,
     *   unique_data: array<string>,
     *   required_data: array<string>,
     * }
     */
    protected array $object_args;

    protected string $id_field;

    protected string $table;

    /**
     * Column names to property names.
     *
     * @var array<string,string>
     */
    protected array $cols_to_props = array();

    /**
     * Meta keys to property names.
     *
     * @var array<string,string>
     */
    protected array $meta_to_props = array();

    /**
     * Taxonomy keys to property names.
     *
     * @var array<string,string>
     */
    protected array $tax_to_props = array();

    /**
     * Taxonomy fields.
     *
     * @var array<string>
     */
    protected array $tax_fields = array();

    /**
     * Data stored in meta keys, but not considered "meta" for an object.
     *
     * @var array<string>
     */
    protected $internal_meta_keys = array();

    /**
     * Meta store.
     *
     * @var ?XWC_Meta_Store<T>
     */
    protected ?XWC_Meta_Store $meta_store;

    public function get_object_type(): string {
        return $this->object_type;
    }

    /**
     * Get the object arguments.
     *
     * @return array{
     *   core_data: array<string,mixed>,
     *   data: array<string,mixed>,
     *   tax_data: array<string,mixed>,
     *   prop_types: array<string,'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'string'|'other'>,
     *   unique_data: array<string>,
     *   required_data: array<string>,
     * }
     */
    public function get_object_args(): array {
        return $this->object_args;
    }

    /**
     * Get the database table for the data store.
     *
     * @return string
     */
    public function get_table() {
        return $this->table;
    }

    public function get_id_field(): string {
        return $this->id_field;
    }

    /**
     * Get the column names to property names mapping.
     *
     * @return array<string,string>
     */
    public function get_cols_to_props(): array {
        return $this->cols_to_props;
    }

    /**
     * Get the meta keys to property names mapping.
     *
     * @return array<string,string>
     */
    public function get_meta_to_props(): array {
        return $this->meta_to_props;
    }

    /**
     * Get the taxonomy keys to property names mapping.
     *
     * @return array<string,string>
     */
    public function get_tax_to_props(): array {
        return $this->tax_to_props;
    }

    /**
     * Get the taxonomy fields.
     *
     * @return array<string>
     */
    public function get_tax_fields(): array {
        return $this->tax_fields;
    }

    /**
     * Get the meta store.
     *
     * @return ?XWC_Meta_Store<T>
     */
    public function get_meta_store(): ?XWC_Meta_Store {
        return $this->meta_store;
    }

    /**
     * Initialize the data store.
     *
     * @template TFc of XWC_Object_Factory<T>
     *
     * @param  Entity<T,static,TFc,XWC_Meta_Store<T>> $e Entity object.
     * @return static
     */
    public function initialize( Entity $e ): static {
        $this->object_type   = $e->name;
        $this->table         = $e->table;
        $this->id_field      = $e->id_field;
        $this->cols_to_props = $e->cols_to_props;
        $this->meta_to_props = $e->meta_to_props;
        $this->tax_to_props  = $e->tax_to_props;
        $this->tax_fields    = $e->tax_fields;
        $this->meta_store    = $e->meta_store;

        $this->internal_meta_keys = \array_keys( $this->meta_to_props );

        // @phpstan-ignore assign.propertyType
        $this->object_args = array(
            'core_data'     => $e->core_data,
            'data'          => $e->data,
            'has_meta'      => $e->has_meta,
            'prop_types'    => $e->prop_types,
            'required_data' => $e->required_data,
            'tax_data'      => $e->tax_data,
            'unique_data'   => $e->unique_data,
        );

        return $this;
    }

    /**
     * {@inheritDoc}
     *
     * @param  T $data Data object.
     * @return void
     */
    public function create( &$data ) {
        $id = $this->persist_save(
            'insert',
            array( 'data' => $data->get_core_data( 'db' ) ),
        );

        if ( ! $id ) {
            throw new \Exception( 'Failed to create Entity' );
        }

        $data->set_id( $id );

        $this->update_prop_data( $data );
        $this->update_meta_data( $data );
        $this->update_term_data( $data, true );
        $this->update_extra_data( $data );
        $this->update_custom_data( $data );
        $this->update_cache_data( $data );

        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_new_' . $this->get_object_type(), $data->get_id(), $data );
    }

    /**
     * {@inheritDoc}
     *
     * @param  T $data Package object.
     * @return void
     *
     * @throws \Exception If invalid Entity.
     */
    public function read( &$data ) {
        $this->read_core_data( $data );
        $this->read_prop_data( $data );
        $this->read_term_data( $data );
        $this->read_extra_data( $data );

        $data->set_object_read( true );

        // Documented in `WC_Data_Store_WP`.
        \do_action( "woocommerce_{$this->get_object_type()}_read", $data->get_id() );
    }

    /**
     * {@inheritDoc}
     *
     * @param  T $data Data Object.
     * @return void
     */
    public function update( &$data ) {
        $changes = $data->get_core_changes();

        if ( $changes ) {
            $this->persist_save(
                'update',
                array(
                    'data'  => $changes,
                    'where' => array( $this->get_id_field() => $data->get_id() ),
                ),
            );
        }

        $this->update_prop_data( $data );
        $this->update_meta_data( $data );
        $this->update_term_data( $data );
        $this->update_extra_data( $data );
        $this->update_custom_data( $data );
        $this->update_cache_data( $data );

        $data->apply_changes();

        // Documented in `WC_Data_Store_WP`.
        \do_action( 'woocommerce_update_' . $this->get_object_type(), $data->get_id(), $data );
    }

    /**
     * {@inheritDoc}
     *
     * @param T       $data Data object.
     * @param  array<string,mixed> $args Array of args to pass to delete method.
     * @return bool                      Result
     */
    public function delete( &$data, $args = array() ) {
        global $wpdb;

        $obj_id = $data->get_id();
        $args   = \wp_parse_args( $args, array( 'force_delete' => false ) );

        if ( ! $obj_id || ! $args['force_delete'] ) {
            return false;
        }

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_before_delete_' . $this->get_object_type(), $obj_id, $args );

        $wpdb->delete( $this->get_table(), array( $this->get_id_field() => $data->get_id() ) );

        $this->delete_all_meta( $data );
        $this->delete_term_data( $data );

        $data->set_id( 0 );

        //phpcs:ignore WooCommerce.Commenting
        \do_action( 'woocommerce_delete_' . $this->get_object_type(), $obj_id, $data, $args );

        return true;
    }

    /**
     * Checks if a value is unique in the database
     *
     * @param  string $prop   Property or column name.
     * @param  mixed  $value  Value to check.
     * @param  int    $obj_id Current ID.
     * @return bool
     */
    public function is_value_unique( mixed $value, string $prop, int $obj_id ): bool {
        global $wpdb;

        $id_f = $this->get_id_field();
        $prop = $this->get_cols_to_props()[ $prop ] ?? $prop;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(%i) FROM %i WHERE %i = %s AND %i != %d',
                $id_f,
                $this->get_table(),
                $prop,
                $value,
                $id_f,
                $obj_id,
            ),
        );

        return 0 === (int) $count;
    }

    public function unique_entity_slug( string $slug, string $prop, int $obj_id ): string {
        global $wpdb;

        $prop = $this->get_cols_to_props()[ $prop ] ?? $prop;

        $check = $wpdb->get_var(
            $wpdb->prepare(
                'SELECT %i FROM %i WHERE %i = %s AND %i != %d LIMIT 1',
                $prop,
                $this->get_table(),
                $prop,
                $slug,
                $this->get_id_field(),
                $obj_id,
            ),
        );

        if ( ! $check ) {
            return $slug;
        }

        $suffix = \preg_match( '/-(\d+)$/', $slug, $matches ) ? $matches[1] : 0;
        ++$suffix;

        $slug = $suffix > 1
            ? \preg_replace( '/-(\d+)$/', "-$suffix", $slug )
            : "{$slug}-1";

        return $this->unique_entity_slug( $slug, $prop, $obj_id );
    }

    /**
     * Save data to the database.
     *
     * @param  'insert'|'update'   $callback Callback method name.
     * @param  array<string,mixed> $args     Arguments for the callback.
     * @return ($callback is 'insert' ? int<0,max> : int|bool)
     */
    protected function persist_save( string $callback, array $args ): int|bool {
        global $wpdb;
        $args['data'] = $this->remap_columns( $args['data'] );

        $res = match ( $callback ) {
            'insert' => $wpdb->insert( $this->get_table(), ...$args ),
            'update' => $wpdb->update( $this->get_table(), ...$args ),
        };

        if ( $res && 'insert' === $callback ) {
            return $wpdb->insert_id;
        }

        return $res;
    }

    /**
     * Remap columns to properties.
     *
     * @param  array<string,mixed> $data Data to remap.
     * @param  bool                $flip Whether to flip the mapping.
     * @return array<string,mixed>        Remapped data.
     */
    protected function remap_columns( array $data, bool $flip = false ): array {
        $map = $this->get_cols_to_props();
        $map = $flip ? \array_flip( $map ) : $map;
        $val = array();

        foreach ( $data as $key => $value ) {
            $val[ $map[ $key ] ?? $key ] = $value;
        }

        return $val;
    }

    /**
     * Read core data for the entity.
     *
     * @param T $data Data object.
     *
     * @throws \Exception If the entity is invalid.
     */
    protected function read_core_data( XWC_Data &$data ): void {
        if ( $data->get_core_data_read() ) {
            return;
        }

        $props = $this->get_data_row( $data->get_id() );
        $props = $this->remap_columns( $props, true );

        $data->set_defaults();
        $data->set_props( $props );
    }

    /**
     * Get a data row from the database.
     *
     * @param  int   $id Entity ID.
     * @return array<string,mixed>
     *
     * @throws \Exception If the entity is invalid.
     */
    protected function get_data_row( int $id ): array {
        global $wpdb;

        $data_row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE {$this->get_id_field()} = %d",
                $this->get_table(),
                $id,
            ),
            ARRAY_A,
        ) ?? false;

        if ( ! $id || ! $data_row ) {
            throw new \Exception( 'Invalid Entity' );
        }

        return $data_row;
    }

    /**
     * Clear caches.
     *
     * @param  T $data Data object.
     * @return void
     */
    protected function update_cache_data( &$data ) {
        \WC_Cache_Helper::invalidate_cache_group( $this->get_object_type() . '_' . $data->get_id() );
    }

    /**
     * Update custom data.
     *
     * @param  T $data Data object.
     * @return void
     */
    protected function update_custom_data( &$data ) {
        // Placeholder for custom data update.
    }
}
