<?php
declare(strict_types=1);
require __DIR__.'/../src/Domain/Accounting.php';
require __DIR__.'/../src/Domain/SubscriptionPolicy.php';
use ResellerPlatform\Domain\Accounting as A;
use ResellerPlatform\Domain\SubscriptionPolicy as S;
$count = 0;
function same(mixed $expected, mixed $actual): void {
    global $count;
    if ($expected !== $actual) throw new RuntimeException('Mismatch: '.var_export([$expected,$actual],true));
    $count++;
}
function throws(callable $f, string $class): void {
    global $count;
    try { $f(); } catch (Throwable $e) { if ($e instanceof $class) { $count++; return; } throw $e; }
    throw new RuntimeException('Expected '.$class);
}
same(['charge'=>1,'remainder'=>0], A::charge(1_000_000,1000));
$carry=0; $total=0;
for ($i=0;$i<1000;$i++) { $v=A::charge(1_000_000,7,$carry); $total+=$v['charge'];$carry=$v['remainder']; }
same(7,$total);same(0,$carry);
same(400_000_000,A::delta(10_000_000_000,10_400_000_000,'a','a'));
throws(fn()=>A::delta(100,50,'a','a'),DomainException::class);
throws(fn()=>A::delta(100,50,'a','b'),DomainException::class);
same(50,A::delta(100,50,'a','b',0));
throws(fn()=>A::charge(PHP_INT_MAX,PHP_INT_MAX),OverflowException::class);
same('active',S::desiredState(false,false,null,100,0,999999,1));
same('wallet_zero',S::desiredState(false,false,null,100,0,0,0));
same('active',S::desiredState(false,false,null,100,0,0,1));
same('manual_suspended',S::desiredState(false,true,null,100,0,0,100));
same('expired',S::desiredState(false,false,99,100,0,0,100));
same('quota_exceeded',S::desiredState(false,false,null,100,10,10,100));
same('deleted',S::desiredState(true,false,null,100,0,0,100));
echo "$count assertions passed\n";
