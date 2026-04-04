<?php

declare(strict_types=1);

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class ReleaseConfigTest extends TestCase {
    public function test_release_config_packages_runtime_files_into_github_asset(): void {
        $config = json_decode((string) file_get_contents(dirname(__DIR__) . '/.releaserc'), true, 512, JSON_THROW_ON_ERROR);

        $exec_plugin = null;

        foreach ($config['plugins'] as $plugin) {
            if (is_array($plugin) && '@semantic-release/exec' === $plugin[0]) {
                $exec_plugin = $plugin[1];
                break;
            }
        }

        $this->assertIsArray($exec_plugin);
        $this->assertArrayHasKey('prepareCmd', $exec_plugin);
        $this->assertStringContainsString('src', $exec_plugin['prepareCmd']);
        $this->assertStringContainsString('lib', $exec_plugin['prepareCmd']);
        $this->assertStringContainsString('composer.json', $exec_plugin['prepareCmd']);
        $this->assertStringContainsString('README.md', $exec_plugin['prepareCmd']);
        $this->assertStringContainsString('LICENSE', $exec_plugin['prepareCmd']);
    }

    public function test_release_config_keeps_beta_as_prerelease_branch(): void {
        $config = json_decode((string) file_get_contents(dirname(__DIR__) . '/.releaserc'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('master', $config['branches'][0]);
        $this->assertContains(
            array(
                'name'       => 'beta',
                'prerelease' => true,
            ),
            $config['branches']
        );
    }
}
