<?php

namespace PavelKrush\Duration;

class Parser {
    private static $unitMap = [
        "ns" => Duration::Nanosecond,
        "us" => Duration::Microsecond,
        "µs" => Duration::Microsecond, // U+00B5 = micro symbol
        "μs" => Duration::Microsecond, // U+03BC = Greek letter mu
        "ms" => Duration::Millisecond,
        "s"  => Duration::Second,
        "m"  => Duration::Minute,
        "h"  => Duration::Hour,
    ];

    /**
     * leadingFraction consumes the leading [0-9]* from s.
     * It is used only for fractions, so does not return an error on overflow,
     * it just stops accumulating precision.
     * @param string $s
     * @return array
     */
    private static function leadingFraction(string $s): array {
        $x = 0;
        $i = 0;
        $scale = 1;
        $overflow = false;
        for (; $i < strlen($s); $i++) {
            $c = $s[$i];
            if (ord($c) < ord('0') || ord($c) > ord('9')) {
                break;
            }
            if ($overflow) {
                continue;
            }
            if ($x > intdiv(PHP_INT_MAX, 10)) {
                // It's possible for overflow to give a positive number, so take care.
                $overflow = true;
                continue;
            }
            $y = $x * 10 + ord($c) - ord('0');
            if ($y < 0) {
                $overflow = true;
                continue;
            }
            $x = $y;
            $scale *= 10;
        }
        return [$x, $scale, substr($s, $i)];
    }
    /**
     * leadingInt consumes the leading [0-9]* from s.
     * @param string $s
     * @return array
     * @throws ErrLeadingInt
     */
    private static function leadingInt(string $s): array {
        $x = 0;
        $i = 0;
        for (; $i < strlen($s); $i++) {
            $c = $s[$i];
            if (ord($c) < ord('0') || ord($c) > ord('9')) {
                break;
            }
            if ($x > intdiv(PHP_INT_MAX, 10)) {
                // overflow
                throw new ErrLeadingInt();
            }
            $x = $x * 10 + ord($c) - ord('0');
            if ($x < 0) {
                // overflow
                // @codeCoverageIgnoreStart
                throw new ErrLeadingInt();
                // @codeCoverageIgnoreEnd
            }
        }
        return [$x, substr($s, $i)];
    }

    /**
     * Parse parses a duration string.
     * A duration string is a possibly signed sequence of
     * decimal numbers, each with optional fraction and a unit suffix,
     * such as "300ms", "-1.5h" or "2h45m".
     * Valid time units are "ns", "us" (or "µs"), "ms", "s", "m", "h".
     * @param string $s
     * @return Duration
     * @throws ParserException
     */
    public static function fromString(string $s): Duration {
        // [-+]?([0-9]*(\.[0-9]*)?[a-z]+)+
        $orig = $s;
        $d = 0;
        $neg = false;

        // Consume [-+]?
        if ($s != "") {
            $c = $s[0];
            if ($c == '-' || $c == '+') {
                $neg = $c == '-';
                $s = substr($s, 1);
            }
        }

        // Special case: if all that is left is "0", this is zero.
        if ($s == "0") {
            return new Duration(0);
        }
        if ($s == "") {
            throw new ParserException("time: invalid duration $orig");
        }

        while ($s != "") {
            $f = 0;
            $scale = 1;

            // The next character must be [0-9.]
            if (!($s[0] == '.' || ord('0') <= ord($s[0]) && ord($s[0]) <= ord('9'))) {
                throw new ParserException("time: invalid duration $orig");
            }
            // Consume [0-9]*
            $pl = strlen($s);
            try {
                list($v, $s) = self::leadingInt($s);
            } catch (ErrLeadingInt $err) {
                throw new ParserException("time: invalid duration $orig", 0, $err);
            }
            $pre = $pl != strlen($s); // whether we consumed anything before a period

            // Consume (\.[0-9]*)?
            $post = false;
            if ($s != "" && $s[0] == '.') {
                $s = substr($s, 1);
                $pl = strlen($s);
                list($f, $scale, $s) = self::leadingFraction($s);
                $post = $pl != strlen($s);
            }
            if (!$pre && !$post) {
                // no digits (e.g. ".s" or "-.s")
                throw new ParserException("time: invalid duration $orig");
            }

            // Consume unit.
            $i = 0;
            for (; $i < strlen($s); $i++) {
                $c = $s[$i];
                if ($c == '.' || ord('0') <= ord($c) && ord($c) <= ord('9')) {
                    break;
                }
            }
            if ($i == 0) {
                throw new ParserException("time: missing unit in duration $orig");
            }
            $u = substr($s, 0, $i);
            $s = substr($s, $i);
            if (!array_key_exists($u, self::$unitMap)) {
                throw new ParserException("time: unknown unit $u in duration $orig");
            }
            $unit = self::$unitMap[$u];
            if ($v > intdiv(PHP_INT_MAX, $unit)) {
                // overflow
                throw new ParserException("time: invalid duration $orig");
            }
            $v *= $unit;
            if ($f > 0) {
                // float64 is needed to be nanosecond accurate for fractions of hours.
                // v >= 0 && (f*unit/scale) <= 3.6e+12 (ns/h, h is the largest unit)
                $v += (int)((float)$f * ((float)$unit / $scale));
                if ($v < 0) {
                    // overflow

                    // @codeCoverageIgnoreStart cannot overflow integer in php
                    throw new ParserException("time: invalid duration $orig");
                    // @codeCoverageIgnoreEnd
                }
            }
            $d += $v;
            if ($d < 0) {
                // overflow
                // @codeCoverageIgnoreStart cannot overflow integer in php
                throw new ParserException("time: invalid duration $orig");
                // @codeCoverageIgnoreEnd
            }
        }

        if ($neg) {
            $d = -$d;
        }
        return new Duration($d);
    }
}
