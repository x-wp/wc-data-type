<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/TestProp.php';

use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class PropTest extends TestCase {
    public function test_default_json_representation_is_null_for_default_data(): void {
        $prop = new XWC_Test_Prop();

        $this->assertNull($prop->jsonSerialize());
    }

    public function test_set_marks_prop_as_changed_after_read(): void {
        $prop = new XWC_Test_Prop();

        $prop->set('alpha', 'b');

        $this->assertTrue($prop->changed());
        $this->assertSame('b', $prop->get('alpha'));
    }

    public function test_set_data_marks_prop_as_changed_after_read(): void {
        $prop = new XWC_Test_Prop();

        $prop->set_data(
            array(
                'alpha' => 'z',
                'items' => array(1, 2, 3),
            ),
        );

        $this->assertTrue($prop->changed());
    }

    public function test_with_data_clones_from_other_prop_instances(): void {
        $source = new XWC_Test_Prop(
            array(
                'alpha' => 'source',
                'items' => array(4, 5),
            ),
        );
        $clone = XWC_Test_Prop::default()->with_data($source);

        $this->assertInstanceOf(XWC_Test_Prop::class, $clone);
        $this->assertNotSame($source, $clone);
        $this->assertSame($source->get_data(), $clone->get_data());
    }

    public function test_serialization_round_trip_preserves_data(): void {
        $prop = new XWC_Test_Prop(
            array(
                'alpha' => 'serialized',
                'items' => array(9, 7),
            ),
        );

        /** @var XWC_Test_Prop $roundTrip */
        $roundTrip = unserialize(serialize($prop));

        $this->assertInstanceOf(XWC_Test_Prop::class, $roundTrip);
        $this->assertSame($prop->get_data(), $roundTrip->get_data());
    }

    public function test_array_access_mutators_throw(): void {
        $prop = new XWC_Test_Prop();

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Do not use this method directly.');

        $prop['alpha'] = 'c';
    }
}
