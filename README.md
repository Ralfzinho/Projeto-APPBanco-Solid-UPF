
# Projeto-APPBanco-Solid-UPF — Refatoração para SOLID (PHP)

Este repositório contém duas versões do mesmo domínio “bancário”:
- `original/`: código original funcional.
- `solid/`: código refatorado aplicando princípios SOLID (SRP, OCP, LSP, ISP, DIP).

## Como rodar
```bash
# Original
php -S localhost:8000 -t original

# ou rodando um script diretamente (se preferir):
# php original/OriginalScript.php (se tiver apenas saída de terminal)

# SOLID — exemplo de uso:
php -r "require 'solid/RefactoredClasses.php'; \$m = criar_dados_mock(); \$ops=\$m['ops']; \$ops->deposit('0001-CC', 100); print_r(\$ops->history());"
