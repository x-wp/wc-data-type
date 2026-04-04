<?php

declare(strict_types=1);

use XWC\Data\Decorators\Model;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class ModelDefinitionTest extends TestCase {
    public function test_model_requires_meta_store_when_meta_props_are_defined(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A concrete meta store class must be provided when meta props are defined');

        new Model(
            'fixture',
            '{{PREFIX}}fixture_items',
            array(
                'name' => array(
                    'default' => '',
                    'type'    => 'string',
                ),
            ),
            array(
                'color' => array(
                    'default' => '',
                    'type'    => 'string',
                ),
            ),
        );
    }

    public function test_model_normalizes_tax_field_id_to_term_id(): void {
        $model = new Model(
            'fixture',
            '{{PREFIX}}fixture_items',
            array(
                'name' => array(
                    'default' => '',
                    'type'    => 'string',
                ),
            ),
            array(),
            array(
                'category' => array(
                    'field'    => 'id',
                    'return'   => 'single',
                    'taxonomy' => 'category',
                ),
            ),
        );

        $this->assertSame('term_single|term_id|category', $model->tax_props['category']['type']);
        $this->assertSame('term_id', $model->tax_props['category']['field']);
    }

    public function test_model_keeps_term_id_tax_field_stable(): void {
        $model = new Model(
            'fixture',
            '{{PREFIX}}fixture_items',
            array(
                'name' => array(
                    'default' => '',
                    'type'    => 'string',
                ),
            ),
            array(),
            array(
                'category' => array(
                    'field'    => 'term_id',
                    'return'   => 'single',
                    'taxonomy' => 'category',
                ),
            ),
        );

        $this->assertSame('term_single|term_id|category', $model->tax_props['category']['type']);
        $this->assertSame('term_id', $model->tax_props['category']['field']);
    }
}
