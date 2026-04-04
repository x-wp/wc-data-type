<?php

namespace XWC\Data\Interfaces;

use XWC_Data;

/**
 * Describes an object factory.
 *
 * @template T of XWC_Data
 */
interface Object_Factory {
    /**
     * Undocumented function
     *
     * @param  T|int|string|false|null         $id Object ID.
     * @return T
     */
    public function make_object( mixed $id ): XWC_Data;

    /**
     * Get an object by ID.
     *
     * @param  T|int|string|false|null         $id Object ID.
     * @return T|null
     */
    public function get_object( mixed $id ): ?XWC_Data;

    /**
     * Get the ID from various input types.
     *
     * @param  mixed    $id Object ID.
     * @return false|int<0,max>
     */
    public function get_id( mixed $id ): int|bool;

    /**
     * Get the class name of a data object by ID.
     *
     * @param  int $id Object ID.
     * @return false|class-string<T>
     */
    public function get_classname( int $id ): bool|string;
}
