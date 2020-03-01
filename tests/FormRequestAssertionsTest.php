<?php

declare(strict_types=1);

namespace VGirol\FormRequestTester\Tests;

use Illuminate\Foundation\Http\FormRequest;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use PHPUnit\Framework\Assert as PHPUnit;
use VGirol\FormRequestTester\TestFormRequests;

class FormRequestAssertionsTest extends TestCase
{
    private $tester;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tester = $this->getTester();
    }

    private function getTester()
    {
        return new class($this->app) extends OrchestraTestCase {
            use TestFormRequests;

            public function __construct($app)
            {
                parent::__construct(null, [], '');
                $this->app = $app;
            }
        };
    }

    private function getFactory($methods = [])
    {
        return function (string $formRequestType, array $args) use ($methods) {
            $mock = \call_user_func_array(
                [$this, 'getMockForAbstractClass'],
                \array_merge(
                    [$formRequestType, $args],
                    ['', true, true, true, \array_keys($methods)]
                )
            );

            foreach ($methods as $name => $returnValue) {
                $mock->expects($this->once())
                    ->method($name)
                    ->willReturn($returnValue);
            }

            return $mock;
        };
    }

    /**
     * @test
     */
    public function validateFormRequestSucceed()
    {
        $factory = $this->getFactory([ 'rules' => ['data' => 'required'] ]);

        $this->tester->setFormRequestFactory(FormRequest::class, $factory);

        $obj = $this->tester->formRequest(FormRequest::class, [ 'data' => 'value' ]);

        PHPUnit::assertSame($this->tester, $obj);

        $obj = $this->tester->assertValidationPassed();

        PHPUnit::assertSame($this->tester, $obj);
    }

    /**
     * @test
     */
    public function validateFormRequestMockSucceed()
    {
        $factory = $this->getFactory([ 'rules' => ['data' => 'required'] ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $oldCount = $this->getCount();
        $obj = $this->tester->assertValidationPassed();

        PHPUnit::assertNotEquals($oldCount, $this->getCount());
        PHPUnit::assertSame($this->tester, $obj);
    }

    /**
     * @test
     */
    public function validateFormRequestMockDoesNotSucceedNotAuthorized()
    {
        $factory = $this->getFactory([ 'authorize' => false ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $this->setAssertionFailure('Form Request is not authorized');

        $this->tester->assertValidationPassed();
    }

    /**
     * @test
     */
    public function validateFormRequestMockDoesNotSucceed()
    {
        $factory = $this->getFactory([ 'rules' => ['data' => 'required'] ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $this->setAssertionFailure('Validation have failed');

        $this->tester->assertValidationPassed();
    }

    /**
     * @test
     */
    public function validateFormRequestMockDoesNotFail()
    {
        $factory = $this->getFactory([ 'rules' => ['data' => 'required'] ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $this->setAssertionFailure('Validation have passed');

        $this->tester->assertValidationFailed();
    }

    /**
     * @test
     */
    public function validateFormRequestMockDoesNotFailNotAuthorized()
    {
        $factory = $this->getFactory([ 'authorize' => false ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $this->setAssertionFailure('Form Request is not authorized');

        $this->tester->assertValidationFailed();
    }

    /**
     * @test
     */
    public function validateFormRequestMockFail()
    {
        $factory = $this->getFactory([ 'rules' => ['data' => 'required'] ]);

        $obj = $this->tester->mockFormRequest(FormRequest::class, [], [], $factory);

        PHPUnit::assertSame($this->tester, $obj);

        $oldCount = $this->getCount();
        $obj = $this->tester->assertValidationFailed();

        PHPUnit::assertNotEquals($oldCount, $this->getCount());
        PHPUnit::assertSame($this->tester, $obj);
    }

    /**
     * @test
     */
    public function assertAuthorizedSucceed()
    {
        $factory = $this->getFactory([ 'authorize' => true, 'rules' => ['data' => 'required'] ]);

        $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        $oldCount = $this->getCount();
        $obj = $this->tester->assertAuthorized();

        PHPUnit::assertNotEquals($oldCount, $this->getCount());
        PHPUnit::assertSame($this->tester, $obj);
    }

    /**
     * @test
     */
    public function assertAuthorizedFail()
    {
        $factory = $this->getFactory([ 'authorize' => false ]);

        $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        $this->setAssertionFailure('Form Request is not authorized');

        $this->tester->assertAuthorized();
    }

    /**
     * @test
     */
    public function assertNotAuthorizedSucceed()
    {
        $factory = $this->getFactory([ 'authorize' => false ]);

        $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        $oldCount = $this->getCount();
        $obj = $this->tester->assertNotAuthorized();

        PHPUnit::assertNotEquals($oldCount, $this->getCount());
        PHPUnit::assertSame($this->tester, $obj);
    }

    /**
     * @test
     */
    public function assertNotAuthorizedFail()
    {
        $factory = $this->getFactory([ 'authorize' => true, 'rules' => ['data' => 'required'] ]);

        $this->tester->mockFormRequest(FormRequest::class, [ 'data' => 'value' ], [], $factory);

        $this->setAssertionFailure('Form Request is authorized');

        $this->tester->assertNotAuthorized();
    }
}
