<?php
namespace App\Providers\Adapters;
final class ProviderException extends \RuntimeException {
    public function __construct(public readonly string $reason, public readonly ?int $httpStatus=null) {parent::__construct($reason);}
}
