<?php

declare(strict_types=1);

use Yoast\PHPUnitPolyfills\TestCases\TestCase;
use XWC\Data\Entity_Manager;

final class SmokeTest extends TestCase {
    public function test_wordpress_woocommerce_and_package_bootstrap_together(): void {
        $this->assertTrue(function_exists('add_action'));
        $this->assertTrue(class_exists('WooCommerce'));
        $this->assertTrue(class_exists(Entity_Manager::class));

        $manager = Entity_Manager::instance();

        $this->assertInstanceOf(Entity_Manager::class, $manager);
    }
}
