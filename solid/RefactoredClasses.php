<?php
declare(strict_types=1);

/**
 * ==========================
 * SOLID Refactor (PHP >= 8.1)
 * ==========================
 * - SRP: Cada classe tem um único motivo de mudança.
 * - OCP: Novo tipo de conta pode ser adicionado sem alterar as existentes.
 * - LSP: Subtipos (Corrente/Poupança) respeitam o mesmo contrato de Conta.
 * - ISP: Interfaces pequenas e específicas (Account, InterestBearing, Logger, Repository).
 * - DIP: Serviços dependem de interfaces (Repository/Logger), não de implementações concretas.
 */

// ---------- Domain Contracts (ISP) ----------

/** Leitura de dados da conta */
interface ReadOnlyAccountInterface {
    public function getNumber(): string;
    public function getOwner(): Client;
    public function getType(): string;
    public function getBalance(): float;
}

/** Operações mutáveis básicas de conta */
interface AccountInterface extends ReadOnlyAccountInterface {
    public function deposit(float $value): void;
    public function withdraw(float $value): void;
    public function transfer(AccountInterface $to, float $value): void;
}

/** Contas que possuem rendimento (ISP) */
interface InterestBearing {
    public function applyYield(int $months = 1): void;
}

// ---------- Cross-cutting ----------

/** Relógio para facilitar testes e inversão de dependência */
interface ClockInterface {
    public function now(): DateTimeImmutable;
}
final class SystemClock implements ClockInterface {
    public function now(): DateTimeImmutable { return new DateTimeImmutable(); }
}

/** Logger de transações (DIP) */
interface TransactionLoggerInterface {
    public function append(Transaction $t): void;
    /** @return Transaction[] */
    public function all(): array;
}
final class InMemoryTransactionLogger implements TransactionLoggerInterface {
    /** @var Transaction[] */
    private array $items = [];
    public function append(Transaction $t): void { $this->items[] = $t; }
    public function all(): array { return $this->items; }
}

/** Repositório de contas (DIP) */
interface AccountRepositoryInterface {
    public function add(AccountInterface $account): void;
    public function remove(string $number): void;
    public function get(string $number): AccountInterface;
    /** @return AccountInterface[] */
    public function all(): array;
}
final class InMemoryAccountRepository implements AccountRepositoryInterface {
    /** @var array<string, AccountInterface> */
    private array $byNumber = [];
    public function add(AccountInterface $account): void { $this->byNumber[$account->getNumber()] = $account; }
    public function remove(string $number): void { unset($this->byNumber[$number]); }
    public function get(string $number): AccountInterface {
        if (!isset($this->byNumber[$number])) {
            throw new AccountNotFound("Conta {$number} não encontrada.");
        }
        return $this->byNumber[$number];
    }
    public function all(): array { return array_values($this->byNumber); }
}

// ---------- Domain Exceptions ----------

class DomainException extends \RuntimeException {}
class InsufficientFunds extends DomainException {}
class AccountNotFound extends DomainException {}
class InvalidArgument extends DomainException {}

// ---------- Entities ----------

final class Client {
    public function __construct(
        public string $name,
        public string $cpf,
        public string $address,
        public string $phone
    ) {}
    public function info(): string {
        return "{$this->name} | CPF: {$this->cpf} | {$this->phone}";
    }
}

/** Classe base de Conta (LSP) */
abstract class Account implements AccountInterface {
    protected float $balance;
    public function __construct(
        protected string $number,
        protected Client $owner,
        protected string $type,
        float $initialBalance = 0.0
    ) {
        $this->balance = max(0, $initialBalance);
    }

    public function getNumber(): string { return $this->number; }
    public function getOwner(): Client { return $this->owner; }
    public function getType(): string { return $this->type; }
    public function getBalance(): float { return $this->balance; }

    public function deposit(float $value): void {
        if ($value <= 0) throw new InvalidArgument("Depósito inválido.");
        $this->balance += $value;
    }

    public function withdraw(float $value): void {
        if ($value <= 0) throw new InvalidArgument("Saque inválido.");
        if ($value > $this->balance) throw new InsufficientFunds("Saldo insuficiente.");
        $this->balance -= $value;
    }

    public function transfer(AccountInterface $to, float $value): void {
        if ($to === $this) throw new InvalidArgument("Conta de destino inválida.");
        $this->withdraw($value);
        $to->deposit($value);
    }
}

final class CheckingAccount extends Account {
    public function __construct(string $number, Client $owner, private float $overdraft, float $initialBalance = 0.0) {
        parent::__construct($number, $owner, 'corrente', $initialBalance);
    }
    public function overdraft(): float { return $this->overdraft; }
    public function withdraw(float $value): void {
        if ($value <= 0) throw new InvalidArgument("Saque inválido.");
        if ($value > $this->balance + $this->overdraft) {
            throw new InsufficientFunds("Saldo + limite insuficiente.");
        }
        $this->balance -= $value;
    }
}

final class SavingsAccount extends Account implements InterestBearing {
    public function __construct(string $number, Client $owner, private float $monthlyYield, float $initialBalance = 0.0) {
        parent::__construct($number, $owner, 'poupanca', $initialBalance);
    }
    public function yieldRate(): float { return $this->monthlyYield; }
    public function applyYield(int $months = 1): void {
        if ($months < 1) return;
        $this->balance *= (1 + $this->monthlyYield) ** $months;
    }
}

// ---------- Value Object ----------

final class Transaction {
    public function __construct(
        public int $id,
        public DateTimeImmutable $date,
        public float $amount,
        public string $type,              // DEPOSITO | SAQUE | TRANSFERENCIA
        public string $fromAccount,
        public ?string $toAccount = null
    ) {}
}

// ---------- Application Service (DIP) ----------

/**
 * OperationService depende de interfaces (Repository/Logger/Clock),
 * não conhece detalhes de armazenamento, nem de apresentação.
 */
final class OperationService {
    private int $seq = 1;
    public function __construct(
        private AccountRepositoryInterface $repo,
        private TransactionLoggerInterface $logger,
        private ClockInterface $clock = new SystemClock()
    ) {}

    public function deposit(string $accountNumber, float $value): void {
        $acc = $this->repo->get($accountNumber);
        $acc->deposit($value);
        $this->log('DEPOSITO', $value, $accountNumber, null);
    }

    public function withdraw(string $accountNumber, float $value): void {
        $acc = $this->repo->get($accountNumber);
        $acc->withdraw($value);
        $this->log('SAQUE', $value, $accountNumber, null);
    }

    public function transfer(string $fromNumber, string $toNumber, float $value): void {
        $from = $this->repo->get($fromNumber);
        $to   = $this->repo->get($toNumber);
        $from->transfer($to, $value);
        $this->log('TRANSFERENCIA', $value, $fromNumber, $toNumber);
    }

    private function log(string $type, float $amount, string $from, ?string $to): void {
        $this->logger->append(
            new Transaction($this->seq++, $this->clock->now(), $amount, $type, $from, $to)
        );
    }

    /** @return Transaction[] */
    public function history(): array {
        return $this->logger->all();
    }
}

// ---------- Composition Root (Mock) ----------

/**
 * criar_dados_mock() agora retorna as dependências injetadas
 * e os objetos de domínio preparados.
 */
function criar_dados_mock(): array {
    $repo   = new InMemoryAccountRepository();
    $logger = new InMemoryTransactionLogger();
    $ops    = new OperationService($repo, $logger);

    $c1 = new Client('Ralf da Silva', '000.111.222-33', 'Rua A, 10', '(51) 90000-0001');
    $c2 = new Client('Thiago Rech',   '444.555.666-77', 'Rua B, 20', '(51) 90000-0002');

    $cc1 = new CheckingAccount('0001-CC', $c1, overdraft: 500.0, initialBalance: 200.0);
    $cp1 = new SavingsAccount('0002-CP',  $c1, monthlyYield: 0.006, initialBalance: 1000.0);
    $cc2 = new CheckingAccount('0003-CC', $c2, overdraft: 300.0, initialBalance: 50.0);

    $repo->add($cc1);
    $repo->add($cp1);
    $repo->add($cc2);

    return [
        'repo'    => $repo,
        'ops'     => $ops,
        'clients' => [$c1, $c2],
        'accounts'=> [$cc1, $cp1, $cc2],
    ];
}
