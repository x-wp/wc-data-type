<?php

namespace XWC\Data\Decorators;

/**
 * Enables modification of an entity.
 *
 * @property string $name
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Model_Modifier extends Model {
    public function __construct(
        ?string $name = null,
        string $data_store = '',
        array $core_props = array(),
        ?string $factory = null,
        string $meta_store = '',
        array $meta_props = array(),
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
