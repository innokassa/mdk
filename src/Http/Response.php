<?php

namespace Innokassa\Http;

/**
 * Ответ от Pangaea API
 */
class Response 
{
    /**
     * @param integer $iCode код ответа
     * @param string $sBody тело ответа
     */
    public function __construct(int $iCode, string $sBody)
    {
        $this->iCode = $iCode;
        $this->sBody = $sBody;
        $this->aBody = json_decode($sBody, true);

        $this->extrudeError();
    }
    
    public function getStatusCode()
    {
        return $this->iCode;
    }

    public function getBodyRaw()
    {
        return $this->sBody;
    }

    public function getBody()
    {
        return $this->aBody;
    }

    public function hasError(): bool
    {
        return ($this->error != null);
    }

    public function getError(): ?Error
    {
        return $this->error;
    }

    //######################################################################
    // PROTECTED
    //######################################################################

    protected $iCode = null;
    protected $sBody = "";
    protected $aBody = null;
    protected $error = null;

    //######################################################################

    protected function extrudeError()
    {
        if($this->getStatusCode() >= 400)
            $this->error = new Error($this);
    }
};
