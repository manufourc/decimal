<?php
/**
 * This file is part of the PrestaShop\Decimal package
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @license   https://opensource.org/licenses/MIT MIT License
 */

namespace PrestaShop\Decimal\Operation;

use PrestaShop\Decimal\Number as DecimalNumber;

/**
 * Computes the addition of two decimal numbers
 */
class Addition
{

    /**
     * Used to make this class use its own addition implementation regardless the availability of BCMath extension
     */
    const USE_OWN_IMPLEMENTATION = true;

    /**
     * Disables the use of BC Math implementation
     * @var bool
     */
    private $forceOwnImplementation = false;

    /**
     * Maximum safe string size in order to be confident
     * that it won't overflow the max int size when operating with it
     * @var int
     */
    private $maxSafeIntStringSize;

    /**
     * @param bool $forceOwnImplementation If true, do not use BC Math implementation
     */
    public function __construct($forceOwnImplementation = false)
    {
        $this->forceOwnImplementation = $forceOwnImplementation;
        $this->maxSafeIntStringSize = strlen((string) PHP_INT_MAX) - 1;
    }

    /**
     * Performs the addition
     *
     * @param DecimalNumber $a
     * @param DecimalNumber $b
     *
     * @return DecimalNumber Result of the addition
     */
    public function compute(DecimalNumber $a, DecimalNumber $b)
    {
        if (!$this->forceOwnImplementation && function_exists('bcadd')) {
            $precision1 = $a->getPrecision();
            $precision2 = $b->getPrecision();
            return new DecimalNumber((string) bcadd($a, $b, max($precision1, $precision2)));
        }

        // if the addend is negative, e.g. 2 + (-1)
        // perform subtraction instead: 2 - 1
        if ($b->isNegative()) {
            return $a->minus(
                $b->toPositive()
            );
        }

        // optimization: 0 + x = x
        if ('0' === (string) $a) {
            return $b;
        }

        // optimization: x + 0 = x
        if ('0' === (string) $b) {
            return $a;
        }

        // pad coefficients with leading/trailing zeroes
        list($coeff1, $coeff2) = $this->normalizeCoefficients($a, $b);

        // compute the coefficient sum
        $sum = $this->addStrings($coeff1, $coeff2);

        // both signs are equal, so we can use either
        $sign = $a->getSign();

        // keep the bigger exponent
        $exponent = max($a->getExponent(), $b->getExponent());

        return new DecimalNumber($sign . $sum, $exponent);
    }

    /**
     * Normalizes coefficients by adding leading or trailing zeroes as needed so that both are the same length
     *
     * @param DecimalNumber $a
     * @param DecimalNumber $b
     *
     * @return array An array containing the normalized coefficients
     */
    private function normalizeCoefficients(DecimalNumber $a, DecimalNumber $b)
    {
        $exp1 = $a->getExponent();
        $exp2 = $b->getExponent();

        $coeff1 = $a->getCoefficient();
        $coeff2 = $b->getCoefficient();

        // add trailing zeroes if needed
        if ($exp1 > $exp2) {
            $coeff2 = str_pad($coeff2, strlen($coeff2) + $exp1 - $exp2, '0', STR_PAD_RIGHT);
        } elseif ($exp1 < $exp2) {
            $coeff1 = str_pad($coeff1, strlen($coeff1) + $exp2 - $exp1, '0', STR_PAD_RIGHT);
        }

        $len1 = strlen($coeff1);
        $len2 = strlen($coeff2);

        // add leading zeroes if needed
        if ($len1 > $len2) {
            $coeff2 = str_pad($coeff2, $len1, '0', STR_PAD_LEFT);
        } elseif ($len1 < $len2) {
            $coeff1 = str_pad($coeff1, $len2, '0', STR_PAD_LEFT);
        }

        return array($coeff1, $coeff2);
    }


    /**
     * Adds two integer numbers as strings.
     *
     * @param string $number1
     * @param string $number2
     * @param bool $fractional [default=false]
     * If true, the numbers will be treated as the fractional part of a number (padded with trailing zeroes).
     * Otherwise, they will be treated as the integer part (padded with leading zeroes).
     *
     * @return string
     */
    private function addStrings($number1, $number2, $fractional = false)
    {
        if ('0' !== $number1[0] && '0' !== $number2[0]) {
            // optimization - numbers can be treated as integers as long as they don't overflow the max int size
            if (strlen($number1) <= $this->maxSafeIntStringSize
                && strlen($number2) <= $this->maxSafeIntStringSize
            ) {
                return (string) ((int) $number1 + (int) $number2);
            }
        }

        // find out which of the strings is longest
        $maxLength = max(strlen($number1), strlen($number2));

        // add leading or trailing zeroes as needed
        $number1 = str_pad($number1, $maxLength, '0', $fractional ? STR_PAD_RIGHT : STR_PAD_LEFT);
        $number2 = str_pad($number2, $maxLength, '0', $fractional ? STR_PAD_RIGHT : STR_PAD_LEFT);

        $result = '';
        $carryOver = 0;
        for ($i = $maxLength - 1; 0 <= $i; $i--) {
            $sum = $number1[$i] + $number2[$i] + $carryOver;

            if ($sum >= 10) {
                $result .= $sum % 10;
                $carryOver = 1;
            } else {
                $result .= $sum;
                $carryOver = 0;
            }
        }
        if ($carryOver > 0) {
            $result .= '1';
        }

        return strrev($result);
    }
}