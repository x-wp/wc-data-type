<?php

declare(strict_types=1);

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class ObjectFactoryTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        xwc_test_reset_custom_entity_table();
    }

    protected function tearDown(): void {
        unset($GLOBALS[xwc_test_custom_entity_name()]);

        parent::tearDown();
    }

    public function test_factory_get_id_accepts_numeric_ids(): void {
        $factory = xwc_get_object_factory(xwc_test_custom_entity_name());

        $this->assertSame(25, $factory->get_id(25));
        $this->assertSame(25, $factory->get_id('25'));
    }

    public function test_factory_get_id_accepts_data_objects_and_globals(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
            ),
        );

        $factory = xwc_get_object_factory(xwc_test_custom_entity_name());
        $object  = new XWC_Test_Item(1);

        $GLOBALS[xwc_test_custom_entity_name()] = $object;

        $this->assertSame(1, $factory->get_id($object));
        $this->assertSame(1, $factory->get_id(null));
    }

    public function test_factory_get_object_returns_null_for_missing_ids(): void {
        $factory = xwc_get_object_factory(xwc_test_custom_entity_name());

        $this->assertNull($factory->get_object(false));
    }

    public function test_factory_make_object_returns_concrete_object_even_for_zero_id(): void {
        $factory = xwc_get_object_factory(xwc_test_custom_entity_name());
        $object  = $factory->make_object(0);

        $this->assertInstanceOf(XWC_Test_Item::class, $object);
        $this->assertSame(0, $object->get_id());
    }

    public function test_object_classname_falls_back_to_base_class_when_filter_returns_invalid_class(): void {
        add_filter(
            'xwc_' . xwc_test_custom_entity_name() . '_class',
            static fn (): string => 'Missing_Class',
        );

        try {
            $this->assertSame(XWC_Data::class, xwc_get_object_classname(1, xwc_test_custom_entity_name()));
        } finally {
            remove_all_filters('xwc_' . xwc_test_custom_entity_name() . '_class');
        }
    }

    public function test_get_object_instance_returns_hydrated_object(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
            ),
        );

        $object = xwc_get_object_instance(1, xwc_test_custom_entity_name());

        $this->assertInstanceOf(XWC_Test_Item::class, $object);
        $this->assertSame('alpha', $object->get_slug());
    }
}
