# PAP Market

Sistema de gestão de supermercado — stock, vendas, RH, caixa e relatórios.

---

## Credenciais de teste

> Página visual: `http://localhost:8000/credenciais.php`

| Role | Email | Password |
|------|-------|----------|
| Admin | admin@papmarket.pt | admin123 |
| Gerente | gerente@papmarket.pt | gerente123 |
| Caixa | caixa@papmarket.pt | caixa123 |
| Funcionário | func@papmarket.pt | func123 |

---

## Base de dados

- **Nome:** `supermercado`
- **Host:** `127.0.0.1` · **Porto:** `3306`
- **Utilizador:** `root` · **Password:** *(em branco)*
- **phpMyAdmin:** `http://localhost:8080` *(correr `php -S localhost:8080 -t /opt/homebrew/share/phpmyadmin`)*

---

Começar (Passo a passo):

1) Importar o esquema da base de dados

   - Usa o ficheiro `schema.sql` para criar as tabelas e dados iniciais. Exemplo:

     mysql -u pap_user -p supermercado < schema.sql

   - Credenciais de exemplo incluídas no dump: `user=pap_user` / `password=pap_pass`.

2) Configurar a ligação à base de dados

   - Edita `config/database.php` para ajustar `DB_HOST`, `DB_NAME`, `DB_USER` e `DB_PASS` conforme o teu ambiente.

3) Instalar dependências PHP

   - Executa: `composer install`

4) Arrancar o servidor de desenvolvimento

   - Usa o script npm/composer para mais conveniência:
     - `composer serve` (arranca o servidor em `127.0.0.1:8000`)
     - Alternativa manual: `php -S 127.0.0.1:8000 -t .`

5) Popular dados de exemplo (opcional)

   - Executa: `composer db:seed` (popula vendas, daily_profit, monthly_top_product e outros exemplos)

6) Testes

   - Executa: `composer test` (ou `./vendor/bin/phpunit`) para correr os testes unitários.

7) Aceder à aplicação

   - Abre: `http://127.0.0.1:8000/` — a página inicial (Dashboard) mostra um resumo financeiro e links para módulos.

Notas e dicas:

- Se o site não responde em `localhost:8000`, tenta `127.0.0.1:8000` (evita problemas de resolução IPv6 `::1`).
- Faz backup da base antes de aplicar alterações de esquema em ambientes de produção.
- Para automatizar: podes adicionar mais scripts em `composer.json` ou criar um Makefile para tarefas frequentes.

Funcionalidades incluídas:
- Scaffold com módulos (`modules/`), includes (`includes/`) e assets (`assets/`).
- CRUD básico para **produtos** e **fornecedores** (adicionar/editar/eliminar/listar).
- Funções de negócio em `includes/functions.php` (vendas com IVA, quebras, encomendas, sumário financeiro).
- Reabastecimento automático (`auto_reorder`) que gera encomendas quando o stock está abaixo do limiar.
- Dashboard simples com gráfico exemplificativo.
- **Página de administração:** `modules/db_status.php` — lista todas as tabelas da base de dados, contagem de registos e verifica tabelas esperadas.

Próximos passos sugeridos:
- Melhorar UI/UX e adicionar validações front-end.
- Adicionar autenticação e permissões.
- Implementar alertas por email e análise de perecíveis.

---

## Como criar um repositório no GitHub, push e abrir um PR (passo a passo) ✨

Siga estes passos para publicar o código no GitHub e criar um Pull Request com as alterações que fiz (branch `fix/migration-add-created_at`):

1) Instalar e autenticar o GitHub CLI (recomendado):

   - macOS (Homebrew):
     - `brew install gh`
     - `gh auth login` (segue as instruções interactivas; escolhe GitHub.com, autenticação via browser e autoriza com o teu usuário)

2) Configurar o remote (criar o repositório no GitHub):

   - Se preferes criar o repositório manualmente no GitHub, cria um repo (ex.: `vascoruas/PAP_projeto`) e depois adiciona o remote:
     - `git remote add origin git@github.com:USERNAME/REPO.git`

   - Ou usar o GitHub CLI para criar e configurar o remote automaticamente:
     - `gh repo create USERNAME/REPO --public --source=. --remote=origin --push`
     - Substitui `USERNAME/REPO` pelo nome desejado (usa `--private` se preferires privado).

3) Fazer push da branch e abrir o PR:

   - `git checkout fix/migration-add-created_at`
   - `git push -u origin fix/migration-add-created_at`
   - Criar o Pull Request com `gh` (recomendado):
     - `gh pr create --fill --title "Ensure orders.created_at migration" --body "Add migration step to ensure orders.created_at exists; also make list_orders tolerant to missing column."`

   - Se preferires, podes criar o PR manualmente pela interface web do GitHub (vai a "Compare & pull request").

4) Notas úteis:

   - Testes locais: antes de abrir o PR executa `composer test` ou `./vendor/bin/phpunit`.
   - Inclui no corpo do PR uma descrição das mudanças e o motivo (já coloquei sugestões no commit message).

Se preferires, faço eu o passo final (criar o repo e o PR) assim que confirmares que tens o `gh` instalado e autenticado, ou se preferires usar outro repositório já existente diz o nome e eu o utilizo.

---

(Adicionei estas instruções para facilitar a subida e o PR — podes editá-las conforme necessário.)
