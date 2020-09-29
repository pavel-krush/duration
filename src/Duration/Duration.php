<?php

namespace PavelKrush\Duration;

class Duration {
    const Nanosecond  = 1;
    const Microsecond = 1000 * self::Nanosecond;
    const Millisecond = 1000 * self::Microsecond;
    const Second      = 1000 * self::Millisecond;
    const Minute      = 60 * self::Second;
    const Hour        = 60 * self::Minute;

    const minDuration = -1 << 63;
    const maxDuration = 1<<63 - 1;

    /** @var int */
    private $value;

    /**
     * Create duration object. Pass duration in nanoseconds
     * Duration constructor.
     * @param int $d
     */
    public function __construct(int $d) {
        $this->value = $d;
    }

    /**
     * String returns a string representing the duration in the form "72h3m0.5s".
     * Leading zero units are omitted. As a special case, durations less than one
     * second format use a smaller unit (milli-, micro-, or nanoseconds) to ensure
     * that the leading digit is non-zero. The zero duration formats as 0s.
     * @return string
     */
    public function __toString(): string {
        // Largest time is 2540400h10m10.000000000s
        $buf = '';

        $u = $this->value;

        $neg = $this->value < 0;
        if ($neg) {
            $u = -$u;
        }

        if ($u < self::Second) {
            // Special case: if duration is smaller than a second,
            // use smaller units, like 1.2ms
            $prec = 0;
            $buf = 's' . $buf;
            if ($u == 0) {
                return "0s";
            }
            else if ($u < self::Microsecond) {
                $prec = 0;
                $buf = 'n' . $buf;
            }
            else if ($u < self::Millisecond) {
                $prec = 3;
                $buf = 'µ' . $buf;
            }
            else {
                $prec = 6;
                $buf = 'm' . $buf;
            }

            list($buf, $u) = $this->fmtFrac($buf, $u, $prec);
            $buf = $this->fmtInt($buf, $u);
        }
        else {
            $buf = 's' . $buf;

            list($buf, $u) = $this->fmtFrac($buf, $u, 9);

            // u is now integer seconds
            $buf = $this->fmtInt($buf, $u % 60);
            $u = intdiv($u, 60);

            // u is now integer minutes
            if ($u > 0) {
                $buf = 'm' . $buf;
                $buf = $this->fmtInt($buf, $u % 60);
                $u = intdiv($u, 60);

                // u is now integer hours
                if ($u > 0) {
                    $buf = 'h' . $buf;
                    $buf = $this->fmtInt($buf, $u);
                }
            }
        }

        if ($neg) {
            $buf = '-' . $buf;
        }

        return $buf;
    }

    /**
     * fmtFrac formats the fraction of v/10**prec (e.g., ".12345") into the
     * tail of buf, omitting trailing zeros. It omits the decimal
     * point too when the fraction is 0. It returns the index where the
     * output bytes begin and the value v/10**prec.
     * @param string $buf
     * @param int $v
     * @param int $prec
     * @return array
     */
    private function fmtFrac(string $buf, int $v, int $prec) {
        // Omit trailing zeros up to and including decimal point.
        //w := len(buf)
        $print = false;
        for ($i = 0; $i < $prec; $i++) {
            $digit = $v % 10;

            if ($digit != 0) {
                $print = true;
            }

            if ($print) {
                $buf = chr(ord('0') + $digit) . $buf;
            }

            $v = intdiv($v, 10);
        }

        if ($print) {
            $buf = "." . $buf;
        }

        return [$buf, $v];
    }

    /**
     * fmtInt formats v into the tail of buf.
     * It returns the index where the output begins.
     * @param string $buf
     * @param int $v
     * @return string
     */
    private function fmtInt(string $buf, int $v) {
        // the whole function can be replaces by single sprintf, but leave original code
        if ($v == 0) {
            $buf = '0' . $buf;
        } else {
            while ($v > 0) {
                $buf = chr(($v % 10) + ord('0')) . $buf;
                $v = intdiv($v, 10);
            }
        }

        return $buf;
    }

    /**
     * Nanoseconds returns the duration as an integer nanosecond count.
     * @return int
     */
    public function Nanoseconds(): int {
        return $this->value;
    }

    /**
     * Microseconds returns the duration as an integer microsecond count.
     * @return int
     */
    public function Microseconds(): int {
        return intdiv($this->value, self::Microsecond);
    }

    /**
     * Milliseconds returns the duration as an integer millisecond count.
     * @return int
     */
    public function Milliseconds(): int {
        return intdiv($this->value, self::Millisecond);
    }

    /**
     * These methods return float64 because the dominant
     * use case is for printing a floating point number like 1.5s, and
     * a truncation to integer would make them not useful in those cases.
     * Splitting the integer and fraction ourselves guarantees that
     * converting the returned float64 to an integer rounds the same
     * way that a pure integer conversion would have, even in cases
     * where, say, float64(d.Nanoseconds())/1e9 would have rounded
     * differently.
     */

    /**
     * Seconds returns the duration as a floating point number of seconds.
     * @return float
     */
    public function Seconds(): float {
        $sec = $this->value / self::Second;
	    $nsec = $this->value % self::Second;
	    return $sec + $nsec/1e9;
    }

    /**
     * Minutes returns the duration as a floating point number of minutes.
     * @return float
     */
    public function Minutes(): float {
        $min = $this->value / self::Minute;
        $nsec = $this->value % self::Minute;
        return $min + $nsec/(60*1e9);
    }

    /**
     * Hours returns the duration as a floating point number of hours.
     * @return float
     */
    public function Hours(): float {
        $hour = $this->value / self::Hour;
        $nsec = $this->value % self::Hour;
        return $hour + $nsec/(60*60*1e9);
    }

    /**
     * Truncate returns the result of rounding d toward zero to a multiple of m.
     * If m <= 0, Truncate returns d unchanged.
     * @param Duration $m
     * @return Duration
     */
    public function Truncate(Duration $m): Duration {
        if ($m->value < 0) {
            return new Duration($this->value);
        }
        return new Duration($this->value - ($this->value % $m->value));
    }

    /**
     * lessThanHalf reports whether x+x < y but avoids overflow,
     * assuming x and y are both positive (Duration is signed).
     * @param Duration $x
     * @param Duration $y
     * @return bool
     */
    private function lessThanHalf(Duration $x, Duration $y): bool {
        return (float)$x->value + (float)$x->value < (float)$y->value;
    }



    /**
     * Round returns the result of rounding d to the nearest multiple of m.
     * The rounding behavior for halfway values is to round away from zero.
     * If the result exceeds the maximum (or minimum)
     * value that can be stored in a Duration,
     * Round returns the maximum (or minimum) duration.
     * If m <= 0, Round returns d unchanged.
     * @param Duration $m
     * @return Duration
     */
    public function Round(Duration $m): Duration {
        if ($m <= 0) {
            return new Duration($this->value);
        }

        $r = $this->value % $m->value;

        if ($this->value < 0) {
            $r = -$r;
            if ($this->lessThanHalf(new Duration($r), $m)) {
                return new Duration($this->value + $r);
            }

            $d1 = $this->value - $m->value + $r;
            if ($d1 < $this->value) {
                return new Duration($d1);
            }

            return new Duration(self::minDuration); // overflow
        }

        if ($this->lessThanHalf(new Duration($r), $m)) {
            return new Duration($this->value - $m->value);
        }

        $d1 = $this->value + $m->value - $r;
        if ($d1 > $this->value) {
            return new Duration($d1);
        }

        return new Duration(self::maxDuration); // overflow
    }
}
