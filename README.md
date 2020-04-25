# FormRequest-Tester

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Software License][ico-license]](LICENSE.md)
[![Build Status][ico-travis]][link-travis]
[![Coverage Status][ico-scrutinizer]][link-scrutinizer]
[![Quality Score][ico-code-quality]][link-code-quality]
[![Infection MSI][ico-mutation]][link-mutation]
[![Total Downloads][ico-downloads]][link-downloads]

This package provides a set of tools to test Laravel FormRequest.
It is strongly inspired by the package [mohammedmanssour/form-request-tester](https://github.com/mohammedmanssour/form-request-tester).

## Technologies

- PHP 7.2+
- Laravel 5.8+

## Install

To install through composer, simply put the following in your `composer.json` file:

```json
{
    "require-dev": {
        "vgirol/formrequest-tester": "dev-master"
    }
}
```

And then run `composer install` from the terminal.

### Quick Installation

Above installation can also be simplified by using the following command:

``` bash
$ composer require vgirol/formrequest-tester
```

## Usage

Assertions can be chained :

``` php
use App\Requests\DummyFormRequest;
use Orchestra\Testbench\TestCase;
use VGirol\FormRequestTesterer\TestFormRequests;

class FormRequestTester extends TestCase
{
    use TestFormRequests;

    /**
     * @test
     */
    public function myFirtsTest()
    {
        // Creates a form
        $form = [
            'data' => [
                'type' => 'dummy',
                'attributes' => [
                    'attr' => 'value'
                ]
            ]
        ];

        // Create and validate form request for DummyFormRequest class
        $this->formRequest(
            DummyFormRequest::class,
            $form,
            [
                'method' => 'POST',
                'route' => '/dummy-route'
            ]
        )->assertValidationPassed();
    }
}
```

## Documentation

The API documentation is available in XHTML format at the url [http://formrequest-tester.girol.fr/docs/ref/index.html](http://FormRequestTester.girol.fr/docs/ref/index.html).

## Change log

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Testing

``` bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) and [CODE_OF_CONDUCT](CODE_OF_CONDUCT.md) for details.

## Security

If you discover any security related issues, please email [vincent@girol.fr](mailto:vincent@girol.fr) instead of using the issue tracker.

## Credits

- [Vincent Girol][link-author]
- [All Contributors][link-contributors]

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

[ico-version]: https://img.shields.io/packagist/v/VGirol/FormRequest-Tester.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/VGirol/FormRequest-Tester/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/VGirol/FormRequest-Tester.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/VGirol/FormRequest-Tester.svg?style=flat-square
[ico-mutation]: https://img.shields.io/endpoint?style=flat-square&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2FVGirol%2FFormRequest-Tester%2Fmaster
[ico-downloads]: https://img.shields.io/packagist/dt/VGirol/FormRequest-Tester.svg?style=flat-square

[link-packagist]: https://packagist.org/packages/VGirol/FormRequest-Tester
[link-travis]: https://travis-ci.org/VGirol/FormRequest-Tester
[link-scrutinizer]: https://scrutinizer-ci.com/g/VGirol/FormRequest-Tester/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/VGirol/FormRequest-Tester
[link-downloads]: https://packagist.org/packages/VGirol/FormRequest-Tester
[link-author]: https://github.com/VGirol
[link-mutation]: https://infection.github.io
[link-contributors]: ../../contributors
