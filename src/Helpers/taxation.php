<?php

namespace Innokassa\Helpers;

/**
 * Статичный класс для работы с налогообложением
 */
class taxation
{
    /**
     * Система налогообложения (Тег 1055)
     */
	const TAXATION = [
		1 => "ОРН",
		2 => "УСН доход",
		4 => "УСН доход - расход",
		16 => "ЕСН",
		32 => "ПСН",
	];

    //######################################################################

    /**
     * Входящие налогообложения в значение
     *
     * @param integer $iNumber
     * @return array ассоциативный массив где ключ число, а значение текст
     */
	static public function getTaxIncluded(int $iNumber): array
	{
		$aTax = [];

		foreach(self::TAXATION as $key => $value)
		{
			if($iNumber & $key)
				$aTax[$key] = $value;
		}

		return $aTax;
	}

    //**********************************************************************

    /**
     * Получить массив всех доступных (вообще) налогообложений (self::TAXATION)
     *
     * @return array
     */
	static public function getTaxAll(): array
	{
		return self::TAXATION;
	}
};
