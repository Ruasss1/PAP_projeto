# 🚀 Roadmap - Sistema de Supermercado Avançado

## 📋 Visão Geral
Transformar o sistema de gestão de supermercado numa solução enterprise-grade com segurança, analytics avançados e múltiplas plataformas (Web, Mobile, Desktop).

---

## 🏗️ Fases de Implementação

### **FASE 1: Segurança & Auditoria (Semana 1-2)**
#### 🔐 Objetivos
- [x] Estrutura de banco de dados para users, roles, permissões
- [ ] Sistema de autenticação (login seguro)
- [ ] Controlo de acesso baseado em roles (RBAC)
- [ ] Logging de auditoria (quem, o quê, quando)

#### 📝 Tabelas a criar
```
users (id, name, email, password_hash, role_id, active, created_at)
roles (id, name, description, created_at)
permissions (id, role_id, resource, action, created_at)
audit_logs (id, user_id, action, resource, changes, ip_address, created_at)
```

#### ✅ Funcionalidades
- Login/logout com sessions
- Dashboard por role (diferentes views)
- Log de todas alterações críticas
- Relatório de auditoria

---

### **FASE 2: Gestão de Preços (✅ 85% COMPLETO)**
#### 💰 Objetivos
- [x] Cálculo automático de preços (markup %)
- [x] Histórico de preços com visualização
- [x] Gestão de promoções sazonais
- [x] Análise de margem por produto/categoria
- [x] Web interface (5 abas)
- [x] Documentação completa

#### 📁 Ficheiros Criados
1. **migrations/003_add_pricing_management.sql** (200 linhas)
2. **includes/pricing.php** (600+ linhas, 20+ funções)
3. **modules/pricing.php** (400+ linhas, UI web)
4. **PRICING_API.md** (documentação)
5. **PRICING_EXAMPLES.md** (14 exemplos)

#### 🗄️ Tabelas Criadas (7)
```
price_strategies       - Markup por produto
promotions            - Descontos e promoções
promotion_products    - Aplicação a produtos
promotion_categories  - Aplicação a categorias
margin_analysis       - Snapshots históricos
price_change_log      - Histórico de mudanças
category_pricing_rules - Regras padrão
```

#### 📊 Funcionalidades Implementadas

**Estratégias de Preço:**
- ✅ Configurar markup % automático
- ✅ Min/max price guardrails
- ✅ Fallback a padrão por categoria
- ✅ Calcular preço venda automaticamente

**Análise de Margem:**
- ✅ Snapshots históricos (30/90 dias)
- ✅ Análise por categoria
- ✅ Detecção de produtos subprecificados
- ✅ Relatórios com KPIs

**Promoções:**
- ✅ Descontos percentuais ou fixos
- ✅ Aplicar a produto/categoria/todos
- ✅ Período de validade
- ✅ Múltiplas promoções simultâneas

**Web Interface (5 abas):**
- ✅ Dashboard: KPIs + Análise por categoria
- ✅ Estratégias: Gestão de markup
- ✅ Promoções: Criar/gerenciar
- ✅ Análise: Histórico de margens
- ✅ Categorias: Regras padrão

**Integração:**
- ✅ AuthManager (autenticação)
- ✅ RBAC (permissões)
- ✅ Audit logging

#### 📈 API (20+ funções)
```
get_price_strategy()
set_price_strategy()
calculate_sell_price()
calculate_margin()
record_margin_analysis()
get_margin_history()
get_category_margin_analysis()
find_underpriced_products()
get_pricing_performance_report()
log_price_change()
get_price_change_history()
create_promotion()
add_product_to_promotion()
add_category_to_promotion()
get_active_promotions_for_product()
apply_promotions()
list_promotions()
get_promotion()
...e mais
```

#### 🏷️ Categorias Pré-configuradas (8)
```
Frutas        35% markup
Padaria       40% markup
Laticínios    30% markup
Mercearia     25% markup
Bebidas       20% markup
Congelados    28% markup
Limpeza       45% markup
Higiene       40% markup
```

#### ⏳ Pendente
- [ ] Modais de edição (estratégias)
- [ ] Modals de criação (promoções)
- [ ] Integração com carrinho de vendas
- [ ] Dashboard analytics avançado
- Gráficos de evolução de preços
- Relatório de produtos mais rentáveis

---

### **FASE 3: Inventário Avançado (⏳ PRÓXIMA - 0% COMPLETO)**
#### 📦 Objetivos
- [ ] Rastreamento de lotes e datas de validade
- [ ] Alertas de expiração (pré-aviso 14 dias)
- [ ] Contagens cíclicas automáticas
- [ ] Análise de rotação de stock FIFO

#### 🗄️ Tabelas a criar (5)
```
product_batches       - Rastreamento de lotes
batch_movements       - Histórico de movimentação
expiry_alerts         - Alertas de validade
cycle_counts          - Ciclos de contagem
inventory_discrepancies - Discrepâncias encontradas
```

#### 📊 Funcionalidades Planeadas

**Gestão de Lotes:**
- Rastreamento por batch/lote
- FIFO automático na saída
- Histórico de movimentação
- Alertas de validade próxima

**Gestão de Validade:**
- Data de validade por lote
- Alertas (14 dias antes)
- Dashboard de produtos expirados
- Relatórios de waste

**Rotação de Stock:**
- Análise FIFO
- Sugestões de promoção
- Previsão de expirações
- Dashboard de risco

**Contagem Cíclica:**
- Ciclos de contagem por setor
- Alertas de discrepâncias
- Relatórios de acurácia
- Ajustes automáticos

#### 📈 API Planeada (15+ funções)
- create_batch()
- add_to_batch()
- get_batch_by_expiry()
- record_batch_movement()
- create_cycle_count()
- check_expiry_alerts()
- analyze_fifo_rotation()
- get_inventory_accuracy()

#### 💻 Ficheiros a Criar
- `migrations/004_add_advanced_inventory.sql`
- `includes/inventory.php`
- `modules/inventory_batches.php`
- `modules/inventory_expiry.php`
- `modules/cycle_counting.php`
- `PHASE_3_INVENTORY.md`
- `INVENTORY_API.md`

**Estimativa:** 1000+ linhas de código

---

### **FASE 4: QR Codes & Barcodes (⏳ PLANEADO - 0%)**
#### 🏷️ Objetivos
- [ ] Geração automática de barcodes (EAN-13)
- [ ] Geração de QR codes
- [ ] Leitura via scanner USB
- [ ] Leitura via câmara

#### 🗄️ Tabelas a criar (2)
```
barcodes          - Registro de códigos
barcode_scans     - Histórico de leituras
```

#### 📊 Funcionalidades Planeadas

**Geração de Códigos:**
- Gerar barcodes (EAN-13)
- Gerar QR codes
- Códigos internos/lotes
- Impressão em massa

**Leitura de Códigos:**
- Scanner integrado (USB)
- Captura via câmara
- Validação automática
- Histórico de leituras

**Rastreamento:**
- Entrada: Recebimento com barcode
- Saída: Vendas com barcode
- Devolução: Rastreamento completo
- Histórico completo por unidade

#### 📚 Bibliotecas
- `picqer/php-barcode-generator` (EAN, CODE128)
- `endroid/qr-code` (QR Code)

#### 💻 Ficheiros a Criar
- `migrations/005_add_barcodes.sql`
- `includes/barcode.php`
- `modules/barcode_generator.php`
- `modules/barcode_scanner.php`
- `PHASE_4_BARCODES.md`

**Estimativa:** 500+ linhas de código

---

### **FASE 5: Analytics Avançado (⏳ PLANEADO - 0%)**
#### 📊 Objetivos
- [ ] Dashboards interativos
- [ ] Relatórios por período
- [ ] Previsões de vendas
- [ ] KPIs integrados

#### 🗄️ Tabelas a criar (2)
```
kpi_snapshots     - Snapshots de KPIs
sales_forecast    - Previsões de vendas
```

#### 📊 Funcionalidades Planeadas

**Dashboards:**
- Sales dashboard (tempo real)
- Inventory dashboard
- Finance dashboard
- Performance dashboard

**Relatórios:**
- Vendas por período
- Top produtos e categorias
- Margin analysis
- Seasonality trends

**Previsões:**
- Sales forecast
- Inventory projection
- Demand planning
- Trend analysis

**KPIs:**
- Revenue, Profit, Growth
- Inventory turnover
- Margin %, Markup %
- Stock accuracy

#### 📚 Bibliotecas
- `Chart.js` (gráficos)
- `ApexCharts` (advanced charts)

#### 💻 Ficheiros a Criar
- `includes/analytics.php`
- `modules/analytics_sales.php`
- `modules/analytics_inventory.php`
- `modules/analytics_finance.php`
- `PHASE_5_ANALYTICS.md`

**Estimativa:** 800+ linhas de código

---

### **FASE 6: RH Avançado (⏳ PLANEADO - 0%)**
#### 👥 Objetivos
- [ ] Gestão de pessoal
- [ ] Folha de pagamento
- [ ] Performance
- [ ] Calendários e escalas

#### 🗄️ Tabelas a criar (4)
```
employees         - Dados de colaboradores
schedules         - Horários e escalas
payroll           - Folhas de pagamento
absences          - Férias e faltas
```

#### 📊 Funcionalidades Planeadas

**Gestão de Pessoal:**
- Ficha de colaborador
- Histórico de cargos
- Horários e escalas
- Férias e faltas

**Folha de Pagamento:**
- Cálculo de salários
- Descontos (IRS, SS)
- Bónus e prémios
- Histórico de pagamentos

**Performance:**
- Avaliações
- KPIs por departamento
- Produtividade
- Incentivos

**Calendário:**
- Agendamento
- Escalas de trabalho
- Horas extras
- Gestão de turnos

#### 💻 Ficheiros a Criar
- `migrations/006_add_hr_advanced.sql`
- `includes/hr.php`
- `modules/hr_employees.php`
- `modules/hr_payroll.php`
- `modules/hr_performance.php`
- `PHASE_6_HR.md`

**Estimativa:** 1000+ linhas de código

---

### **FASE 7: API REST (⏳ PLANEADO - 0%)**
#### 🔌 Objetivos
- [ ] API RESTful completa
- [ ] Autenticação JWT
- [ ] Rate limiting
- [ ] Documentação Swagger

#### 📡 Endpoints Principais
```
Authentication:
  POST /api/auth/login
  POST /api/auth/logout
  GET /api/auth/verify

Products:
  GET /api/products
  GET /api/products/:id
  POST /api/products
  PUT /api/products/:id

Pricing:
  GET /api/pricing/:id
  PUT /api/pricing/:id
  GET /api/promotions

Sales:
  GET /api/sales
  POST /api/sales
  GET /api/sales/:id

Inventory:
  GET /api/inventory
  POST /api/inventory/adjust
  GET /api/inventory/:id

Users:
  GET /api/users
  POST /api/users
  PUT /api/users/:id
```

#### 📊 Funcionalidades Planeadas

**Autenticação:**
- JWT stateless
- Refresh tokens
- API key support

**Segurança:**
- Rate limiting
- CORS
- Input validation
- Error handling

**Documentação:**
- Swagger/OpenAPI
- Postman collection
- API changelog

#### 💻 Ficheiros a Criar
- `api/v1/auth.php`
- `api/v1/products.php`
- `api/v1/pricing.php`
- `api/v1/sales.php`
- `api/v1/inventory.php`
- `api/v1/users.php`
- `API_DOCUMENTATION.md`

**Estimativa:** 1200+ linhas de código

---

### **FASE 8: Mobile & Desktop Apps (⏳ PLANEADO - 0%)**
#### 💻 Objetivos
- [ ] Mobile app (React Native)
- [ ] Desktop app (Electron)
- [ ] PWA web app
- [ ] Sincronização offline

#### 📱 Mobile App
**Stack:** React Native

**Funcionalidades:**
- Catálogo de produtos
- Carrinho de compras
- Histórico de vendas
- Notificações push
- Offline mode

#### 🖥️ Desktop App
**Stack:** Electron + React

**Funcionalidades:**
- Gestão completa
- Relatórios offline
- Sincronização
- Impressoras
- Scanners

#### 🌐 Web PWA
**Funcionalidades:**
- Instalável
- Offline first
- Fast loading
- Push notifications

#### 💻 Ficheiros a Criar
- `mobile/` (React Native project)
- `desktop/` (Electron project)
- `PHASE_8_APPS.md`

**Estimativa:** 2000+ linhas de código

---

## 📊 Status Geral por PHASE

| PHASE | Funcionalidade | Status | Ficheiros | Documentação |
|-------|---|---|---|---|
| 1 | Segurança & Auditoria | ✅ 100% | 5 | 3 docs |
| 2 | Gestão de Preços | ✅ 85% | 7 | 3 docs |
| 3 | Inventário Avançado | ⏳ 0% | - | - |
| 4 | Barcodes & QR | ⏳ 0% | - | - |
| 5 | Analytics Avançado | ⏳ 0% | - | - |
| 6 | RH Avançado | ⏳ 0% | - | - |
| 7 | API REST | ⏳ 0% | - | - |
| 8 | Mobile/Desktop | ⏳ 0% | - | - |

**Total Código:** 2200+ linhas (PHASE 1-2)  
**Documentação:** 8 ficheiros  
**Próximas Etapas:** PHASE 3 (Inventário)

---

## 🎯 Prioridades por Role

### **ADMIN**
- Acesso completo
- Auditoria total
- Gestão de users
- Relatórios executivos

### **GERENTE**
- Vendas, stock, financeiro
- Relatórios de desempenho
- Gestão de promoções
- RH básico

### **CAIXA**
- Vendas apenas
- Consulta de stock/preços
- Histórico da sessão
- Sem acesso a relatórios

### **STOCK**
- Inventário completo
- Entrada de mercadorias
- Contagens cíclicas
- Sem acesso a vendas

### **RH**
- Gestão de funcionários
- Horários e folgas
- Folhas de pagamento
- Sem acesso a vendas

---

## 🔒 Padrões de Segurança

✅ **Implementado (PHASE 1)**
- Senhas com hash (bcrypt, cost=12)
- SQL Injection prevention (prepared statements)
- CSRF tokens
- Rate limiting
- HTTPS obrigatório
- Logging de acesso
- Backup automático
- Permissões por row-level

---

## 📱 Tecnologias Adicionais

| Componente | Tech | Razão |
|-----------|------|-------|
| Barcode Gen | `php-barcode-generator` | Fácil geração |
| QR Code | `phpqrcode` ou `qrcode.js` | Leitura via câmara |
| PDF Export | `mPDF` ou `TCPDF` | Relatórios |
| Charts | `Recharts` (já tem) | Análise visual |
| Desktop | `Electron` | Multiplataforma |
| Mobile | React + PWA | Web responsivo |
| ML | `php-ml` | Previsões simples |
| Push | Firebase Cloud Messaging | Notificações |

---

## 🚀 Próximos Passos

1. ✅ Planificação (este documento)
2. ⏭️ **FASE 1**: Estrutura DB + Auth + RBAC
3. Teste completo de cada fase
4. Deploy progressivo
5. Documentação ao longo do caminho

---

## 📞 Suporte & Questões

Para dúvidas sobre implementação específica, consulte:
- `/migrations/` - Schema updates
- `/includes/functions.php` - Business logic
- `/modules/` - Feature modules
- OpenAPI spec - API docs

---

**Última atualização**: 14 de Janeiro de 2026
**Status**: Planificação completa ✅ | Implementação: Pronta
