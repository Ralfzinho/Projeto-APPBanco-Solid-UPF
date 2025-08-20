Projeto Banco – Refatoração com SOLID

Este projeto demonstra a aplicação de princípios SOLID sobre um código de exemplo de sistema bancário.

Abaixo, cada classe é documentada com a versão original, o princípio aplicado e a versão refatorada.

Como Executar
Versão original
php -S localhost:8000 -t original
# abra http://localhost:8000/OriginalScript.php

Versão SOLID (exemplo rápido em linha de comando)
php -r "require 'solid/RefactoredClasses.php';
$m=criar_dados_mock(); $ops=$m['ops'];
$ops->deposit('0001-CC', 100);
print_r($ops->history());"

Conta
a) Classe Original
// original/OriginalClasses.php (trecho)
abstract class Conta {
    protected float $saldo;
    public function __construct(
        protected string  $numero,
        protected Cliente $cliente,
        protected string  $tipo,
        float $saldoInicial = 0.0
    ) { $this->saldo = max(0, $saldoInicial); }

    public function depositar(float $valor): void { /* valida e soma ao saldo */ }
    public function sacar(float $valor): void { /* valida e subtrai do saldo */ }
    public function transferir(Conta $destino, float $valor): void {
        $this->sacar($valor);
        $destino->depositar($valor);
    }
}

class ContaCorrente extends Conta {
    public function __construct(string $numero, Cliente $cliente, private float $limite, float $saldoInicial=0.0) {
        parent::__construct($numero,$cliente,'corrente',$saldoInicial);
    }
    public function sacar(float $valor): void {
        if ($valor > $this->saldo + $this->limite) throw new SaldoInsuficienteException();
        $this->saldo -= $valor;
    }
}

class ContaPoupanca extends Conta {
    public function __construct(string $numero, Cliente $cliente, private float $rendimentoAoMes, float $saldoInicial=0.0) {
        parent::__construct($numero,$cliente,'poupanca',$saldoInicial);
    }
    public function aplicarRendimento(int $meses=1): void {
        $this->saldo *= (1 + $this->rendimentoAoMes) ** max(1,$meses);
    }
}

b) Princípio SOLID Aplicado

LSP: as subclasses mantêm o contrato de Conta (podem ser usadas no lugar da classe base).

OCP: adicionar um novo tipo de conta requer criar uma nova subclasse, sem alterar as existentes.

ISP: extraímos interfaces pequenas para separar leitura/uso de recursos extras (ex.: rendimento).

SRP: a classe cuida apenas das regras de uma conta; log e persistência ficam fora.

c) Classe Refatorada
// solid/RefactoredClasses.php (trecho)
interface ReadOnlyAccountInterface {
    public function getNumber(): string;
    public function getOwner(): Client;
    public function getType(): string;
    public function getBalance(): float;
}
interface AccountInterface extends ReadOnlyAccountInterface {
    public function deposit(float $value): void;
    public function withdraw(float $value): void;
    public function transfer(AccountInterface $to, float $value): void;
}
interface InterestBearing { public function applyYield(int $months=1): void; }

abstract class Account implements AccountInterface { /* mesmas regras essenciais */ }
final class CheckingAccount extends Account { /* saque com overdraft */ }
final class SavingsAccount  extends Account implements InterestBearing { /* rendimento */ }

Operação → OperationService
a) Classe Original
class Operacao {
    /** @var Transacao[] */ private array $log = [];
    private int $seq = 1;

    public function depositar(Conta $c, float $v): void { $c->depositar($v); $this->log[] = ...; }
    public function sacar(Conta $c, float $v): void { $c->sacar($v); $this->log[] = ...; }
    public function transferir(Conta $o, Conta $d, float $v): void { $o->transferir($d,$v); $this->log[] = ...; }

    public function getLog(): array { return $this->log; }
}

b) Princípio SOLID Aplicado

DIP: o serviço agora depende de interfaces (AccountRepositoryInterface, TransactionLoggerInterface, ClockInterface), não de implementações concretas.

SRP: coordena operações e registra transações; não armazena contas nem se preocupa com UI/saída.

c) Classe Refatorada
interface AccountRepositoryInterface {
    public function add(AccountInterface $a): void;
    public function remove(string $n): void;
    public function get(string $n): AccountInterface;
    /** @return AccountInterface[] */ public function all(): array;
}
interface TransactionLoggerInterface {
    public function append(Transaction $t): void;
    /** @return Transaction[] */ public function all(): array;
}
interface ClockInterface { public function now(): DateTimeImmutable; }
final class SystemClock implements ClockInterface { public function now(): DateTimeImmutable { return new DateTimeImmutable(); } }

final class OperationService {
    private int $seq = 1;
    public function __construct(
        private AccountRepositoryInterface $repo,
        private TransactionLoggerInterface $logger,
        private ClockInterface $clock = new SystemClock()
    ) {}
    public function deposit(string $acc, float $v): void {
        $a=$this->repo->get($acc); $a->deposit($v);
        $this->logger->append(new Transaction($this->seq++,$this->clock->now(),$v,'DEPOSITO',$acc));
    }
    // withdraw(), transfer() análogas…
}

Banco → Repositório
a) Classe Original
class Banco {
    /** @var array<string, Conta> */ private array $contas = [];
    public function criarConta(Conta $c): void { $this->contas[$c->getNumero()] = $c; }
    public function buscarConta(string $n): Conta { /* get por número */ }
    /** @return Conta[] */ public function listarContas(): array { return array_values($this->contas); }
}

b) Princípio SOLID Aplicado

DIP/SRP: criamos a abstração AccountRepositoryInterface e uma implementação em memória (InMemoryAccountRepository).
O repositório só armazena e recupera contas; regras ficam no serviço.

c) Classe Refatorada
final class InMemoryAccountRepository implements AccountRepositoryInterface {
    /** @var array<string, AccountInterface> */ private array $byNumber = [];
    public function add(AccountInterface $a): void { $this->byNumber[$a->getNumber()] = $a; }
    public function remove(string $n): void { unset($this->byNumber[$n]); }
    public function get(string $n): AccountInterface {
        if (!isset($this->byNumber[$n])) throw new AccountNotFound("Conta {$n} não encontrada.");
        return $this->byNumber[$n];
    }
    public function all(): array { return array_values($this->byNumber); }
}

Transação → Transaction + Logger
a) Classe Original
class Transacao {
    public function __construct(
        public int $id, public DateTimeImmutable $data, public float $valor,
        public string $tipo, public string $contaNumero, public ?string $contaDestinoNumero=null
    ) {}
    public static function registrar(/* ... */): self { /* cria com data atual */ }
}

b) Princípio SOLID Aplicado

SRP: Transaction é apenas um Value Object.
O histórico fica em um logger com interface (TransactionLoggerInterface), permitindo trocar por arquivo, DB, etc.

DIP: OperationService não sabe como o log é armazenado, apenas usa a interface.

c) Classe Refatorada
final class Transaction {
    public function __construct(
        public int $id,
        public DateTimeImmutable $date,
        public float $amount,
        public string $type,           // DEPOSITO | SAQUE | TRANSFERENCIA
        public string $fromAccount,
        public ?string $toAccount=null
    ) {}
}
final class InMemoryTransactionLogger implements TransactionLoggerInterface {
    /** @var Transaction[] */ private array $items = [];
    public function append(Transaction $t): void { $this->items[]=$t; }
    public function all(): array { return $this->items; }
}

Cliente
a) Classe Original
class Cliente {
    public function __construct(public string $nome, public string $cpf, public string $endereco, public string $telefone) {}
    public function getInfo(): string { return "{$this->nome} | CPF: {$this->cpf} | {$this->telefone}"; }
}

b) Princípio SOLID Aplicado

SRP: Entidade simples, focada apenas em dados e uma apresentação mínima.

(Opcional) Mudança de idioma e naming consistente (Client::info()).

c) Classe Refatorada
final class Client {
    public function __construct(public string $name, public string $cpf, public string $address, public string $phone) {}
    public function info(): string { return "{$this->name} | CPF: {$this->cpf} | {$this->phone}"; }
}