<?php

declare(strict_types=1);

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class DataObjectTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        xwc_test_reset_custom_entity_table();
    }

    public function test_dynamic_accessors_round_trip_core_props(): void {
        $item = new XWC_Test_Item(0);

        $item->set_name('Alpha');
        $item->set_slug('alpha-item');
        $item->set_score('42');

        $this->assertSame('Alpha', $item->get_name());
        $this->assertSame('alpha-item', $item->get_slug());
        $this->assertSame(42, $item->get_score());
    }

    public function test_hydrated_item_reports_core_data_and_core_changes(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
            ),
        );

        $item = new XWC_Test_Item(1);
        $item->set_score(15);

        $this->assertSame(
            array(
                'name'  => 'Alpha',
                'slug'  => 'alpha',
                'score' => 15,
                'id'    => 1,
            ),
            $item->get_core_data('view', true),
        );
        $this->assertSame(
            array(
                'score' => 15,
            ),
            $item->get_core_changes(),
        );
    }

    public function test_json_serialize_returns_object_data_without_meta_data_key(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
            ),
        );

        $item = new XWC_Test_Item(1);
        $data = $item->jsonSerialize();

        $this->assertSame(1, $data['id']);
        $this->assertSame('Alpha', $data['name']);
        $this->assertArrayNotHasKey('meta_data', $data);
    }

    public function test_serialization_round_trip_restores_object_identity(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
            ),
        );

        /** @var XWC_Test_Item $restored */
        $restored = unserialize(serialize(new XWC_Test_Item(1)));

        $this->assertInstanceOf(XWC_Test_Item::class, $restored);
        $this->assertSame(1, $restored->get_id());
        $this->assertSame('alpha', $restored->get_slug());
    }

    public function test_object_helpers_return_defaults_and_paged_results(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
                array( 'name' => 'Gamma', 'slug' => 'gamma', 'score' => 30 ),
            ),
        );

        $missing = xwc_get_object(999, xwc_test_custom_entity_name(), null);
        $paged   = xwc_get_objects(
            xwc_test_custom_entity_name(),
            array(
                'limit'    => 2,
                'order'    => 'ASC',
                'orderby'  => 'score',
                'paginate' => true,
                'return'   => 'ids',
            ),
        );

        $this->assertNull($missing);
        $this->assertSame(array(1, 2), $paged['objects']);
        $this->assertSame(2, $paged['pages']);
        $this->assertSame(3, $paged['total']);
    }
}
