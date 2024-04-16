<?php //phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
namespace XWC\Interfaces\Enums;

/**
 * Describe an enum that has a label.
 *
 * @property-read string $value The string value of the enum.
 */
interface Labelable {
    /**
     * Get a label for the string backed enum.
     *
     * @return string
     */
    public function getLabel(): string;
}
