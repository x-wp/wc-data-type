<?php

namespace XWC\Data\Decorators;

use XWC_Data;
use XWC_Data_Store_XT;
use XWC_Meta_Store;
use XWC_Object_Factory;

/**
 * Enables modification of an entity.
 *
 * @template TData of XWC_Data
 * @template TDstr of XWC_Data_Store_XT
 * @template TFact of XWC_Object_Factory
 * @template TMeta of XWC_Meta_Store
 *
 * @property string $name
 *
 * @extends Model<TData,TDstr,TFact,TMeta>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Model_Modifier extends Model {
    /**
     * Constructor.
     *
     * @param  string                   $name       Data object name.
     * @param  class-string<TDstr>|null $data_store Data store class name.
     * @param  array<string,string|array{
     *   name: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default: mixed,
     *   unique: bool,
     *   def_cb?: callable(): mixed,
     * }>                               $core_props Array of core properties.
     * @param  class-string<TFact>|null $factory    Object factory class name.
     * @param  class-string<TMeta>|null $meta_store Meta store class name.
     *
     * @param  array<string,array{
     *   name: string,
     *   type: 'date_created'|'date_updated'|'date'|'bool'|'bool_int'|'enum'|'term_single'|'term_array'|'array_assoc'|'array_set'|'array'|'binary'|'base64_string'|'json_obj'|'json'|'int'|'float'|'slug'|'other'|string|class-string,
     *   default: mixed,
     *   unique: bool,
     *   required: bool,
     * }>                               $meta_props Array of meta properties.
     * @param  array<string,array{
     *   taxonomy: string,
     *   field?: 'term_id'|'slug'|'name'|'parent',
     *   default?: string|array<string>|array<int>,
     *   return?: 'single'|'array',
     *   required?: bool
     * }>                               $tax_props  Array of taxonomy properties.
     * @param  string|null              $container  XWP-DI Container ID.
     */
    public function __construct(
        ?string $name = null,
        ?string $data_store = null,
        array $core_props = array(),
        ?string $factory = null,
        ?string $meta_store = null,
        array $meta_props = array(),
        array $tax_props = array(),
        ?string $container = null,
    ) {
        $this->name = $name;

        $table    = '';
        $id_field = '';

        $this->scaffold(
            \compact(
                'table',
                'data_store',
                'factory',
                'core_props',
                'id_field',
                'meta_store',
                'meta_props',
                'tax_props',
                'container',
            ),
        );
    }

    protected function scaffold( array $args ): void {
        foreach ( $this->get_definers() as $prop => $setter ) {
            $this->$prop = $args[ $prop ] || \is_null( $args[ $prop ] )
                ? $this->$setter( $args[ $prop ] )
                : $args[ $prop ];
        }
    }
}
