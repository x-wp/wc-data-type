<?php

declare(strict_types=1);

use XWC\Data\Decorators\Model;

#[Model(
    name: 'xwc_test_item',
    table: '{{PREFIX}}xwc_test_items',
    core_props: array(
        'name' => array(
            'default' => '',
            'type'    => 'string',
        ),
        'slug' => array(
            'default' => '',
            'type'    => 'slug',
        ),
        'score' => array(
            'default' => 0,
            'type'    => 'int',
        ),
    ),
)]
final class XWC_Test_Item extends XWC_Data {
    protected $object_type = 'xwc_test_item';
}
