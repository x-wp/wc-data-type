<?php

declare(strict_types=1);

final class XWC_Test_Prop extends XWC_Prop {
    protected function default_data(): array {
        return array(
            'alpha' => 'a',
            'items' => array(3, 1, 2),
        );
    }
}
