<?php

namespace Innokassa\Helpers;

use Innokassa\Entities\Receipt;
use Innokassa\Exceptions\ReceiptException;

/**
 * Статичный класс для работы с НДС
 */
class vat
{
    /**
     * Налогообложение на котором возможен НДС (ОРН)
     */
    const TAXATION_WITH_VAT = 1;

    /**
     * Значение НДС 0%
     */
    const VAT_0             = 5;

    /**
     * значение "НДС не облагается"
     */
    const WITHOUT_VAT       = 6;

    //**********************************************************************

    //! Ставка НДС (Тег 1199)
	const VAT = [
        1 => "20",
        2 => "10",
        3 => "20/120",
        4 => "10/110",
        5 => "0",
        6 => "0",
	];

    //######################################################################

    //! возвращает значение ставки НДС для API
    static public function forApi(int $iTaxation, int $iVat, string $sCheckType): int
    {
        if($iTaxation == self::TAXATION_WITH_VAT)
		{
			$vat = intval($iVat);

            if($vat == 0)
                return self::VAT_0;

			if($sCheckType == Receipt::TYPE_COMING_PRE)
				$vat = "$vat/1$vat";

            if(!in_array($vat, self::VAT))
                throw new ReceiptException("Не надено значение НДС для API (tax=$iTaxation, vat=$iVat, check type=$sCheckType");

			return intval(array_search($vat, self::VAT));
		}
		
		return self::WITHOUT_VAT;
    }

    //! возвращает значение НДС в процентах из значения для API
    static public function fromApi(int $iVat)
    {
        if(!isset(self::VAT[$iVat]))
            throw new ReceiptException("Не надено значение НДС (from api=$iVat)");

        return self::VAT[$iVat];
    }
}
