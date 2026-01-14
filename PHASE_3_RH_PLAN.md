# 🚀 PHASE 3 - Gestão de Recursos Humanos

## 📌 Visão Geral

Após completar a PHASE 2 (Gestão de Preços) e implementar o sistema de autenticação robusto, estamos prontos para a PHASE 3.

**PHASE 3 focará em**: Gestão Completa de Recursos Humanos (RH)

---

## 🎯 Objetivos da PHASE 3

### 1. **Módulo de Gestão de Colaboradores**
   - Criar, editar, deletar colaboradores
   - Dados pessoais completos
   - Documentação (contrato, seguros, etc.)
   - Histórico de alterações

### 2. **Gestão de Horários**
   - Criar turnos e horários
   - Atribuir a colaboradores
   - Controlo de presença
   - Alertas de ausências

### 3. **Gestão de Férias e Faltas**
   - Pedidos de férias
   - Aprovação/rejeição
   - Controlo de saldo de dias
   - Registo de faltas justificadas

### 4. **Avaliações de Desempenho**
   - Criar avaliações periódicas
   - Scores de desempenho
   - Histórico de avaliações
   - Relatórios de evolução

### 5. **Gestão Salarial (Integrada com Vendas)**
   - Cálculo de salários base
   - Comissões variáveis (baseadas em vendas)
   - Descontos automáticos
   - Recibos de vencimento

### 6. **Documentação e Conformidade**
   - Gestão de documentos
   - Assinatura digital
   - Histórico de acessos
   - Auditoria de RH

### 7. **Dashboards e Relatórios**
   - Dashboard de RH (visão geral)
   - Relatório de custos de pessoal
   - Análise de produtividade
   - Gráficos de turnover

---

## 📊 Estrutura de Dados - PHASE 3

### Novas Tabelas a Criar

```
employees
├── id
├── user_id (FK -> users)
├── name
├── email
├── phone
├── birth_date
├── nif (Número de Identificação Fiscal)
├── address
├── hire_date
├── contract_type
├── department
├── position
├── salary_base
├── status (active/inactive)
└── created_at

schedules
├── id
├── employee_id (FK -> employees)
├── week_day
├── start_time
├── end_time
├── break_duration
└── created_at

time_tracking
├── id
├── employee_id
├── check_in_time
├── check_out_time
├── duration_hours
├── overtime_hours
└── date

vacation_requests
├── id
├── employee_id
├── start_date
├── end_date
├── days_count
├── status (pending/approved/rejected)
├── approved_by
└── created_at

absences
├── id
├── employee_id
├── date
├── reason
├── justified (yes/no)
└── created_at

salaries
├── id
├── employee_id
├── month
├── year
├── base_salary
├── bonus
├── commission (do módulo de vendas)
├── deductions
├── net_salary
├── status (draft/approved/paid)
└── created_at

performance_evaluations
├── id
├── employee_id
├── evaluator_id
├── evaluation_date
├── score (1-5)
├── comments
└── created_at
```

---

## 🔄 Integração com Fases Anteriores

### Com PHASE 1 (Autenticação)
- ✅ Cada colaborador terá login (user_id FK)
- ✅ Permissões baseadas em role (Admin, RH, etc.)
- ✅ Auditoria de todas as ações

### Com PHASE 2 (Preços)
- ✅ Comissões baseadas em vendas
- ✅ Análise de custo-benefício
- ✅ Margens de lucro considerando custos de RH

---

## 📈 Funcionalidades Principais

### 1. Dashboard de RH
```
[Resumo Total]
├── Total de Colaboradores: 25
├── Presentes Hoje: 23
├── Férias Activas: 2
├── Folha de Pagamento: €5.450
└── Taxa de Turnover: 2.1%

[Gráficos]
├── Distribuição por Departamento
├── Cargos Mais Comuns
├── Evolução de Custos
└── Presença por Dia
```

### 2. Gestão de Colaboradores
```
[Listagem]
├── Filtro por Departamento
├── Filtro por Status
├── Pesquisa por Nome
└── Ações Rápidas (Editar, Deletar, Ver Histórico)

[Formulário Novo Colaborador]
├── Dados Pessoais
├── Dados Contratuais
├── Configuração de Login
└── Atribuição de Permissões
```

### 3. Relatório de Vendas x RH
```
Integration com vendas.php
├── Comissões Automáticas
├── Ranking de Vendedores
├── Performance por Período
└── Projeção Salarial
```

---

## 🛠️ Tecnologias

- **Backend**: PHP 8.2 + PDO
- **BD**: MariaDB/MySQL
- **Frontend**: HTML5 + CSS3 + JavaScript
- **Gráficos**: Chart.js para dashboards
- **PDF**: TCPDF para recibos de vencimento
- **Autenticação**: Sistema existente (PHASE 1)

---

## 📋 Timeline Estimado

| Semana | Tarefa | Status |
|--------|--------|--------|
| 1 | Estrutura de BD | 📋 Planeado |
| 1-2 | CRUD Colaboradores | 📋 Planeado |
| 2 | Gestão de Horários | 📋 Planeado |
| 2-3 | Férias e Faltas | 📋 Planeado |
| 3 | Avaliações | 📋 Planeado |
| 3-4 | Salários + Comissões | 📋 Planeado |
| 4 | Dashboards | 📋 Planeado |
| 4 | Testes e Deploy | 📋 Planeado |

---

## 🎬 Próximas Ações

1. ✅ Completar PHASE 1 (Autenticação) - **CONCLUÍDO**
2. ✅ Completar PHASE 2 (Preços) - **CONCLUÍDO**
3. ✅ Criar Sistema de Login Robusto - **EM PROGRESSO**
4. ⏳ Iniciar PHASE 3 (RH) - **AGUARDANDO APROVAÇÃO**

---

## 💾 Ficheiros que Serão Criados

```
modules/
├── rh.php (módulo principal)
├── rh/
│   ├── employees.php
│   ├── schedules.php
│   ├── vacation.php
│   ├── salaries.php
│   ├── performance.php
│   └── reports.php
├── forms/
│   ├── employee_form.php
│   ├── schedule_form.php
│   └── salary_calculator.php
└── api/
    ├── employees_api.php
    ├── schedules_api.php
    └── salaries_api.php

includes/
├── rh_functions.php (funções de RH)
├── salary_calculator.php (cálculo de salários)
└── rh_reports.php (relatórios)

assets/
├── rh_dashboard.css
├── rh_forms.css
└── rh_charts.js
```

---

## ✅ Condições para Iniciar PHASE 3

- [x] PHASE 1 Completa (Autenticação + RBAC)
- [x] PHASE 2 Completa (Gestão de Preços)
- [x] Sistema de Login Robusto
- [x] Tabelas de Autenticação Criadas
- [ ] Aprovação do Utilizador

---

## 🎯 Quer Iniciar a PHASE 3?

Se respondeu **SIM**, vou:

1. ✅ Criar estrutura completa de BD para RH
2. ✅ Desenvolver módulo RH com CRUD completo
3. ✅ Implementar gestão de horários
4. ✅ Criar sistema de férias automático
5. ✅ Integrar com vendas para comissões
6. ✅ Desenvolver dashboards de RH
7. ✅ Criar relatórios detalhados
8. ✅ Documentar tudo com exemplos

---

**Status**: 🟡 AGUARDANDO APROVAÇÃO PARA INICIAR

*Data: 14 de janeiro de 2026 | Versão: 1.0*
