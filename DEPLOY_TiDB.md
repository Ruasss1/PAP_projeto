# Pôr o Mercantec online com TiDB Cloud

## O que já está feito (código)
- `config/database.php` — agora lê um ficheiro `.env` e liga ao MySQL/TiDB **com TLS** (obrigatório no TiDB). Localmente (127.0.0.1) continua a ligar sem TLS, como antes.
- `.env` — já tem os dados do teu TiDB (host, porta 4000, user, password, nome da BD, CA).

Não precisas de mexer em mais código. Só falta meter a base de dados no TiDB.

## Passo 1 — Exportar a tua base de dados local (XAMPP)
Isto só tu o podes fazer (a BD está no teu computador):
1. Liga o MySQL no XAMPP.
2. Abre `http://localhost/phpmyadmin`.
3. Clica na base de dados `supermercado` (à esquerda).
4. Separador **Exportar** → método **Personalizado** → formato **SQL**.
5. Em "Opções de criação de objetos", marca **"Adicionar DROP TABLE..."** (ajuda na reimportação) e deixa o resto por defeito.
6. **Executar** → guarda o ficheiro como `supermercado.sql`.

> Dica: guarda o `supermercado.sql` dentro da pasta do projeto — assim eu posso prepará-lo para o TiDB se quiseres.

## Passo 2 — Importar no TiDB
1. Na consola do TiDB, cria a base de dados (separador SQL/Chat2Query ou no Import escolhe criar): `CREATE DATABASE supermercado;`
2. Separador **Import → Upload a local file** → escolhe `supermercado.sql` → base de dados de destino `supermercado` → **Start Import**.
3. Espera terminar.

## Passo 3 — Onde correr o PHP
O TiDB é só a base de dados. O PHP tem de estar num sítio que **permita ligações de saída** para a porta 4000 com TLS:
- Para testar já: corre o projeto **localmente** (XAMPP/PHP) — com este `.env` a apontar para o TiDB, o teu PHP local liga ao TiDB na cloud.
- Para ficar online 24/7: um PaaS como **Render**, **Koyeb** ou **Fly.io**, ou um **VPS** (ex.: Hetzner + Coolify). Aí defines as mesmas variáveis (`DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`) nas *Environment Variables* do serviço.
- Aviso: alojamento grátis tipo InfinityFree costuma **bloquear** ligações MySQL externas — com TiDB não serve.

## Segurança
Como a password do TiDB foi partilhada, antes de entregar gera uma nova no TiDB ("Generate Password") e atualiza-a no `.env`.
