<?php
declare(strict_types=1);

/** Exceções específicas */
class SaldoInsuficienteException extends RuntimeException {}
class ContaNaoEncontradaException extends RuntimeException {}

/** Cliente */
class Cliente {
    public function __construct(
        public string $nome,
        public string $cpf,
        public string $endereco,
        public string $telefone
    ) {}
    public function getInfo(): string {
        return "{$this->nome} | CPF: {$this->cpf} | {$this->telefone}";
    }
}

/** Conta (abstrata) */
abstract class Conta {
    protected float $saldo;
    public function __construct(
        protected string  $numero,
        protected Cliente $cliente,
        protected string  $tipo,
        float $saldoInicial = 0.0
    ) { $this->saldo = max(0, $saldoInicial); }

    public function getNumero(): string   { return $this->numero; }
    public function getCliente(): Cliente { return $this->cliente; }
    public function getTipo(): string     { return $this->tipo; }
    public function getSaldo(): float     { return $this->saldo; }

    public function depositar(float $valor): void {
        if ($valor <= 0) throw new InvalidArgumentException("Depósito inválido.");
        $this->saldo += $valor;
    }
    public function sacar(float $valor): void {
        if ($valor <= 0) throw new InvalidArgumentException("Saque inválido.");
        if ($valor > $this->saldo) throw new SaldoInsuficienteException("Saldo insuficiente.");
        $this->saldo -= $valor;
    }
    public function transferir(Conta $destino, float $valor): void {
        if ($destino === $this) throw new InvalidArgumentException("Conta de destino inválida.");
        $this->sacar($valor);
        $destino->depositar($valor);
    }
}

/** Conta Corrente */
class ContaCorrente extends Conta {
    public function __construct(
        string $numero, Cliente $cliente, private float $limite, float $saldoInicial = 0.0
    ) { parent::__construct($numero, $cliente, 'corrente', $saldoInicial); }
    public function getLimite(): float { return $this->limite; }
    public function sacar(float $valor): void {
        if ($valor <= 0) throw new InvalidArgumentException("Saque inválido.");
        if ($valor > $this->saldo + $this->limite) {
            throw new SaldoInsuficienteException("Saldo + limite insuficiente.");
        }
        $this->saldo -= $valor;
    }
}

/** Conta Poupança */
class ContaPoupanca extends Conta {
    public function __construct(
        string $numero, Cliente $cliente, private float $rendimentoAoMes, float $saldoInicial = 0.0
    ) { parent::__construct($numero, $cliente, 'poupanca', $saldoInicial); }
    public function getRendimento(): float { return $this->rendimentoAoMes; }
    public function aplicarRendimento(int $meses = 1): void {
        if ($meses < 1) return;
        $this->saldo *= (1 + $this->rendimentoAoMes) ** $meses;
    }
}

/** Transação */
class Transacao {
    public function __construct(
        public int $id,
        public DateTimeImmutable $data,
        public float $valor,
        public string $tipo,              // DEPOSITO | SAQUE | TRANSFERENCIA
        public string $contaNumero,
        public ?string $contaDestinoNumero = null
    ) {}
    public static function registrar(
        int $id, string $tipo, float $valor, string $contaNumero, ?string $contaDestinoNumero = null
    ): self {
        return new self($id, new DateTimeImmutable(), $valor, $tipo, $contaNumero, $contaDestinoNumero);
    }
}

/** Operação (gera transações) */
class Operacao {
    /** @var Transacao[] */
    private array $log = [];
    private int $seq = 1;

    /** @return Transacao[] */
    public function getLog(): array { return $this->log; }

    public function depositar(Conta $conta, float $valor): void {
        $conta->depositar($valor);
        $this->log[] = Transacao::registrar($this->seq++, 'DEPOSITO', $valor, $conta->getNumero());
    }
    public function sacar(Conta $conta, float $valor): void {
        $conta->sacar($valor);
        $this->log[] = Transacao::registrar($this->seq++, 'SAQUE', $valor, $conta->getNumero());
    }
    public function transferir(Conta $origem, Conta $destino, float $valor): void {
        $origem->transferir($destino, $valor);
        $this->log[] = Transacao::registrar($this->seq++, 'TRANSFERENCIA', $valor, $origem->getNumero(), $destino->getNumero());
    }
}

/** Banco (repositório em memória) */
class Banco {
    /** @var array<string, Conta> */ private array $contas = [];
    /** @var Cliente[] */ private array $clientes = [];
    public function __construct(public string $nome) {}
    public function adicionarCliente(Cliente $c): void { $this->clientes[] = $c; }
    /** @return Cliente[] */ public function listarClientes(): array { return $this->clientes; }
    public function criarConta(Conta $conta): void { $this->contas[$conta->getNumero()] = $conta; }
    public function fecharConta(string $numero): void { unset($this->contas[$numero]); }
    /** @return Conta[] */ public function listarContas(): array { return array_values($this->contas); }
    public function buscarConta(string $numero): Conta {
        if (!isset($this->contas[$numero])) throw new ContaNaoEncontradaException("Conta {$numero} não encontrada.");
        return $this->contas[$numero];
    }
}

/** ===== MOCK ===== */
function criar_dados_mock(): array {
    $banco = new Banco('Banco Exemplo');
    $op    = new Operacao();

    $cli1 = new Cliente('Ralf da Silva', '000.111.222-33', 'Rua A, 10', '(51) 90000-0001');
    $cli2 = new Cliente('Thiago Rech',   '444.555.666-77', 'Rua B, 20', '(51) 90000-0002');
    $cli3 = new Cliente('Thomás Moojen',        '111.222.333-44', 'Rua C, 30', '(51) 90000-0003');
    $banco->adicionarCliente($cli1);
    $banco->adicionarCliente($cli2);
    $banco->adicionarCliente($cli3);

    $cc1 = new ContaCorrente('0001-CC', $cli1, 500.0, 200.0);
    $cp1 = new ContaPoupanca('0002-CP', $cli2, 0.006, 1000.0);
    $cc2 = new ContaCorrente('0003-CC', $cli3, 300.0, 50.0);

    $banco->criarConta($cc1);
    $banco->criarConta($cp1);
    $banco->criarConta($cc2);

    return [
        'banco'    => $banco,
        'operacao' => $op,
        'clientes' => [$cli1, $cli2],
        'contas'   => [$cc1, $cp1, $cc2],
    ];
}
