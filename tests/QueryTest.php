<?php

declare(strict_types=1);

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class QueryTest extends TestCase {
    protected function setUp(): void {
        parent::setUp();

        xwc_test_reset_custom_entity_table();
    }

    public function test_custom_query_count_returns_total_for_a_paged_query(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array(
                    'id'    => 1,
                    'name'  => 'Alpha',
                    'slug'  => 'alpha',
                    'score' => 10,
                ),
                array(
                    'id'    => 2,
                    'name'  => 'Beta',
                    'slug'  => 'beta',
                    'score' => 20,
                ),
                array(
                    'id'    => 3,
                    'name'  => 'Gamma',
                    'slug'  => 'gamma',
                    'score' => 30,
                ),
            ),
        );

        $query = new XWC_Object_Query(
            xwc_test_custom_entity_table_name(),
            'id',
        );

        $this->assertSame(
            3,
            $query->count(
                array(
                    'fields'   => 'ids',
                    'page'     => 1,
                    'per_page' => 2,
                ),
            ),
        );
        $this->assertSame(3, $query->total);
    }

    public function test_custom_query_can_filter_rows_without_paging(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
                array( 'name' => 'Gamma', 'slug' => 'gamma', 'score' => 20 ),
            ),
        );

        $query = new XWC_Object_Query(
            xwc_test_custom_entity_table_name(),
            'id',
        );

        $this->assertSame(
            array(2, 3),
            $query->query(
                array(
                    'col_query' => array(
                        'score' => 20,
                    ),
                    'fields'   => 'ids',
                    'order'    => 'ASC',
                    'orderby'  => 'id',
                    'per_page' => 0,
                ),
            ),
        );
        $this->assertSame(2, $query->total);
        $this->assertSame(1, $query->pages);
    }

    public function test_xwc_ds_returns_registered_repo_instance(): void {
        $repo = xwc_ds(xwc_test_custom_entity_name());

        $this->assertInstanceOf(XWC_Data_Store_XT::class, $repo);
        $this->assertSame(xwc_test_custom_entity_table_name(), $repo->get_table());
    }

    public function test_repo_query_paginates_and_returns_ids(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
                array( 'name' => 'Gamma', 'slug' => 'gamma', 'score' => 30 ),
            ),
        );

        $results = xwc_ds(xwc_test_custom_entity_name())->query(
            array(
                'limit'    => 2,
                'order'    => 'ASC',
                'orderby'  => 'score',
                'paginate' => true,
                'return'   => 'ids',
            ),
        );

        $this->assertSame(2, $results['pages']);
        $this->assertSame(3, $results['total']);
        $this->assertCount(2, $results['objects']);
        $this->assertSame(array(1, 2), $results['objects']);
    }

    public function test_repo_query_can_return_hydrated_objects(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
            ),
        );

        $results = xwc_ds(xwc_test_custom_entity_name())->query(
            array(
                'order'   => 'ASC',
                'orderby' => 'score',
                'return'  => 'objects',
            ),
        );

        $this->assertCount(2, $results);
        $this->assertInstanceOf(XWC_Test_Item::class, $results[0]);
        $this->assertSame('alpha', $results[0]->get_slug());
        $this->assertSame(20, $results[1]->get_score());
    }

    public function test_repo_count_applies_core_column_filters(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
                array( 'name' => 'Gamma', 'slug' => 'gamma', 'score' => 20 ),
            ),
        );

        $count = xwc_ds(xwc_test_custom_entity_name())->count(
            array(
                'score' => 20,
            ),
        );

        $this->assertSame(2, $count);
    }

    public function test_repo_find_returns_first_matching_object(): void {
        xwc_test_seed_custom_entity_rows(
            array(
                array( 'name' => 'Alpha', 'slug' => 'alpha', 'score' => 10 ),
                array( 'name' => 'Beta', 'slug' => 'beta', 'score' => 20 ),
            ),
        );

        $found = xwc_ds(xwc_test_custom_entity_name())->find(
            array(
                'slug' => 'beta',
            ),
        );

        $this->assertInstanceOf(XWC_Test_Item::class, $found);
        $this->assertSame(2, $found->get_id());
        $this->assertSame('Beta', $found->get_name());
    }

    public function test_xwc_ds_throws_for_unknown_entities(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing-entity');

        xwc_ds('missing-entity');
    }

    public function test_xwc_get_object_factory_throws_for_unknown_entities(): void {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('missing-entity');

        xwc_get_object_factory('missing-entity');
    }
}
