<?php

namespace Innokassa\Bridge;

/**
 * Интерфейс настроек
 */
interface SettingsInterface
{
    /**
     * Идентификатор актора
     *
     * @return integer
     */
    public function getActorId(): int;

    /**
     * Токена актора
     *
     * @return integer
     */
    public function getActorToken(): string;

    /**
     * Группа касс
     *
     * @return integer
     */
    public function getCashbox(): int;

    /**
     * Сайт (место расчетов)
     *
     * @return string
     */
    public function getSite(): string;

    /**
     * Налогообложение
     *
     * @return integer
     */
    public function getTaxation(): int;

    /**
     * Нужно ли пробивать только второй чек
     *
     * @return boolean
     */
    public function getOnly2(): bool;

    /**
     * Является ли касса агентской
     *
     * @return boolean
     */
    public function getAgent(): bool;

    /**
     * Получить произвольную настройку
     *
     * @param string $name название настройки
     * @throws Innokassa\Exceptions\SettingsException
     * @return string
     */
    public function get(string $name);
};
