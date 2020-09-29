<?php

namespace PavelKrush\Duration;

use Throwable;

class ErrLeadingInt extends \Exception {
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null) {
        parent::__construct("time: bad [0-9]*", $code, $previous); // never printed
    }
}
