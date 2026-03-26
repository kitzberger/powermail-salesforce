<?php
declare(strict_types=1);

namespace TRAW\PowermailSalesforce\Domain\Model;

class Form extends \In2code\Powermail\Domain\Model\Form
{
    protected string $sfOid = '';

    protected int $sfEnable = 0;

    protected string $sfMode = 'web2lead';

    protected string $sfRecordTypeId = '';

    public function getSfOid(): string
    {
        return $this->sfOid;
    }

    public function setSfOid(string $sfOid): void
    {
        $this->sfOid = $sfOid;
    }

    public function getSfEnable(): int
    {
        return $this->sfEnable;
    }

    public function setSfEnable(int $sfEnable): void
    {
        $this->sfEnable = $sfEnable;
    }

    public function getSfMode(): string
    {
        return $this->sfMode;
    }

    public function setSfMode(string $sfMode): void
    {
        $this->sfMode = $sfMode;
    }

    public function getSfRecordTypeId(): string
    {
        return $this->sfRecordTypeId;
    }

    public function setSfRecordTypeId(string $sfRecordTypeId): void
    {
        $this->sfRecordTypeId = $sfRecordTypeId;
    }

    public function getSfFormProperties(): array
    {
        return [
            'enable' => $this->sfEnable,
            'oid' => $this->sfOid,
            'mode' => $this->sfMode,
            'recordTypeId' => $this->sfRecordTypeId,
        ];
    }
}
