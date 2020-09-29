# Duration

PHP implementation of GoLang's time.Duration

The code is mostly ported from GoLang source code. See time/format.go and time/time.go

## Setup

Ensure you have composer installed, then run the following command:

```bash
composer require pavel-krush/duration
```

That will fetch the library inside your vendor folder.
Then you can add the following to your .php files in order to use the library:
```php
require_once __DIR__.'/vendor/autoload.php';
```

## Usage

To parse string containing duration use Parser class:

```php
$d = \PavelKrush\Duration\Parser::fromString("13h10m21s");
print $d->Hours() . "\n"; // 13.345
print $d->Minutes() . "\n"; // 790.7
print $d->Seconds() . "\n"; // 47421
print $d->Round(new \PavelKrush\Duration\Duration(\PavelKrush\Duration\Duration::Minute)); // 13h10m0s 
```

