# 🚀 PHASE 4 - Gestão de Recursos Humanos (RH)

## ❓ Você Quer Prosseguir para PHASE 4?

**Status Actual:**
- ✅ PHASE 1: Security & Auditoria - **CONCLUÍDO**
- ✅ PHASE 2: Gestão de Preços - **CONCLUÍDO**
- ✅ PHASE 3: Login Robusto - **CONCLUÍDO**
- ⏳ PHASE 4: Gestão de RH - **PRONTO PARA INICIAR**

---

## 📋 O Que Será Implementado em PHASE 4

### 1️⃣ **Módulo de Gestão de Colaboradores**

**Funcionalidades:**
- ✅ CRUD completo (Criar, Ler, Atualizar, Deletar)
- ✅ Campos: Nome, Email, NIF, Telefone, Morada
- ✅ Data de contratação e tipo de contrato
- ✅ Departamento e cargo
- ✅ Status (Ativo/Inativo)
- ✅ Upload de documentos (Contrato, CV, Seguros)
- ✅ Histórico de alterações (quem modificou, quando)

**Interface:**
```
Painel de Colaboradores
├── Listagem com filtros
├── Busca por nome/email
├── Cards com resumo
├── Botões: Editar, Ver Detalhes, Deletar
└── Formulário responsivo para novo colaborador
```

---

### 2️⃣ **Gestão de Horários e Turnos**

**Funcionalidades:**
- ✅ Criar turnos (Manhã, Tarde, Noite)
- ✅ Definir horas de início/fim
- ✅ Duração de pausa automática
- ✅ Atribuir colaboradores a turnos
- ✅ Visualização em calendário
- ✅ Alertas de conflitos (mesma pessoa em 2 turnos)
- ✅ Exportar horários em PDF

**Exemplo:**
```
Colaborador: João Silva
┌─────────────────────────────────────┐
│ Seg  │ Ter  │ Qua  │ Qui  │ Sex      │
├─────────────────────────────────────┤
│ 09:00│ 09:00│ 14:00│ 09:00│ 09:00   │
│ 17:00│ 17:00│ 22:00│ 17:00│ 17:00   │
└─────────────────────────────────────┘
```

---

### 3️⃣ **Gestão de Férias e Faltas**

**Funcionalidades:**
- ✅ Pedidos de férias online
- ✅ Visualizar saldo de dias
- ✅ Aprovação/Rejeição automática ou manual
- ✅ Configurar dias de falta
- ✅ Justificação de faltas
- ✅ Relatório de presença
- ✅ Alertas de ausências

**Fluxo:**
```
Colaborador solicita férias
        ↓
Sistema verifica saldo
        ↓
(Automático) Aprova se há dias
        ↓
(Se necessário) Envia para aprovação de RH
        ↓
Confirmação por email
```

---

### 4️⃣ **Gestão de Salários e Comissões**

**Funcionalidades:**
- ✅ Cálculo automático de salários
- ✅ Integração com vendas (comissões variáveis)
- ✅ Descontos automáticos (Segurança Social, IRS)
- ✅ Benefícios e subsídios
- ✅ Recibos de vencimento (PDF)
- ✅ Histórico de salários
- ✅ Previsões de folha de pagamento

**Cálculo de Comissões (Integrado com PHASE 2):**
```
Vendedor: Maria Santos

Salário Base: €800
├── Vendas Junho: €5.000
├── Comissão (2%): €100
├── Bonus Performance: €50
├── Subtotal: €950

Descontos:
├── Segurança Social (11%): €104,50
├── IRS (Estimado): €150
├── Subtotal: €254,50

Salário Líquido: €695,50
```

---

### 5️⃣ **Avaliações de Desempenho**

**Funcionalidades:**
- ✅ Criar avaliações periódicas (Semestral/Anual)
- ✅ Critérios de avaliação (Qualidade, Pontualidade, Atitude)
- ✅ Score de 1-5
- ✅ Comentários do avaliador
- ✅ Plano de melhoria
- ✅ Histórico de avaliações
- ✅ Gráficos de evolução

---

### 6️⃣ **Dashboards e Relatórios**

**Dashboard Principal de RH:**
```
┌─────────────────────────────────────┐
│           DASHBOARD RH              │
├─────────────────────────────────────┤
│ Total de Colaboradores:    25       │
│ Presentes Hoje:            23       │
│ Férias Activas:            2        │
│ Folha de Pagamento:        €5.450   │
│ Taxa de Turnover:          2.1%     │
├─────────────────────────────────────┤
│ [Gráfico] Distribuição Departamentos│
│ [Gráfico] Cargos Mais Comuns        │
│ [Gráfico] Evolução de Custos        │
│ [Gráfico] Presença por Dia          │
└─────────────────────────────────────┘
```

**Relatórios Disponíveis:**
- 📊 Relatório de Custos de Pessoal
- 📊 Análise de Produtividade
- 📊 Turnover e Retenção
- 📊 Distribuição Demográfica
- 📊 Comparação Período vs Período

---

## 🗄️ Estrutura de Dados - PHASE 4

### Novas Tabelas:

```sql
employees
├── id
├── user_id (FK -> users)
├── name
├── email
├── phone
├── nif
├── address
├── hire_date
├── contract_type
├── department
├── position
├── salary_base
├── status
└── created_at

schedules
├── id
├── employee_id
├── week_day
├── start_time
├── end_time
└── break_duration

time_tracking
├── id
├── employee_id
├── check_in
├── check_out
└── date

vacation_requests
├── id
├── employee_id
├── start_date
├── end_date
├── days_count
└── status

absences
├── id
├── employee_id
├── date
├── reason
└── justified

salaries
├── id
├── employee_id
├── month/year
├── base_salary
├── commission
├── deductions
├── net_salary
└── status

performance_evaluations
├── id
├── employee_id
├── evaluator_id
├── evaluation_date
├── score (1-5)
└── comments
```

---

## 🔄 Integração com Fases Anteriores

### Com PHASE 2 (Gestão de Preços):
- ✅ Cada colaborador tem acesso baseado em role
- ✅ Comissões calculadas automaticamente com base em vendas
- ✅ Impacto nos custos de produção

### Com PHASE 1 (Segurança):
- ✅ Autenticação e autorização
- ✅ Audit logging de todos os acessos
- ✅ Papéis específicos (Admin, RH, Gerente)

---

## 📈 Timeline Estimado (PHASE 4)

| Semana | Tarefa | Estimativa |
|--------|--------|-----------|
| 1 | BD + CRUD Colaboradores | 2-3 dias |
| 1-2 | Gestão de Horários | 2-3 dias |
| 2 | Férias e Faltas | 2 dias |
| 2-3 | Salários + Comissões | 3-4 dias |
| 3 | Avaliações | 2 dias |
| 3-4 | Dashboards e Relatórios | 3-4 dias |
| 4 | Testes e Polimento | 2 dias |

**Total: ~20-25 dias (1 mês)**

---

## 📂 Ficheiros que Serão Criados

```
modules/
├── rh.php (módulo principal)
└── rh/
    ├── employees.php (CRUD)
    ├── schedules.php (Horários)
    ├── vacation.php (Férias)
    ├── absences.php (Faltas)
    ├── salaries.php (Salários)
    ├── evaluations.php (Avaliações)
    └── reports.php (Relatórios)

includes/
├── rh_functions.php
├── salary_calculator.php
├── commission_calculator.php
└── rh_reports.php

assets/
├── rh.css (estilos)
├── rh_dashboard.js (gráficos)
└── rh_forms.js (validação)
```

---

## 🎯 Recursos Necessários

### Frontend
- ✅ HTML5 + CSS3
- ✅ JavaScript vanilla (sem dependências)
- ✅ Chart.js (para gráficos)
- ✅ Calendar.js (para horários)

### Backend
- ✅ PHP 8.2
- ✅ MySQL/MariaDB
- ✅ PDO
- ✅ TCPDF (para gerar PDFs)

### Ferramentas
- ✅ Git (versionamento)
- ✅ phpUnit (testes)
- ✅ Postman (testar API)

---

## ✅ Checklist de PHASE 4

- [ ] Criar tabelas de BD
- [ ] Implementar CRUD de colaboradores
- [ ] Criar formulários responsivos
- [ ] Gestão de horários
- [ ] Sistema de férias
- [ ] Cálculo de salários
- [ ] Integração com vendas
- [ ] Avaliações
- [ ] Dashboards
- [ ] Relatórios
- [ ] Testes
- [ ] Documentação
- [ ] Polimento UI/UX

---

## 💡 Diferenciais

O sistema de RH será:
- ✅ Completamente automatizado
- ✅ Integrado com PHASE 2 (comissões)
- ✅ Responsivo em mobile
- ✅ Com audit trail completo
- ✅ Relatórios em tempo real
- ✅ Exportação em PDF/Excel
- ✅ Notificações por email

---

## 🎬 Próximos Passos

### Se Respondeu SIM:
1. ✅ Vou criar estrutura de BD completa
2. ✅ Implementar CRUD de colaboradores
3. ✅ Desenvolver gestão de horários
4. ✅ Criar sistema de férias automático
5. ✅ Integrar comissões com vendas
6. ✅ Desenvolver dashboards
7. ✅ Criar relatórios detalhados
8. ✅ Documentar tudo

### Se Respondeu NÃO:
- Podemos focar em melhorias adicionais em PHASE 2
- Ou outras funcionalidades específicas

---

## 📞 Dúvidas?

Consulte:
- `PHASE_3_RH_PLAN.md` - Plano detalhado
- `AUTHENTICATION_GUIDE.md` - Autenticação
- Outros documentos de referência

---

## 🎯 DECISÃO FINAL

**Quer que eu comece a PHASE 4 (RH) agora?**

Responda com:
- ✅ **SIM** - Proceder com PHASE 4 imediatamente
- ❌ **NÃO** - Focar em outras melhorias
- 🤔 **MAIS INFO** - Quer saber mais sobre algum aspecto?

---

*Data: 14 de janeiro de 2026*  
*Versão: 1.0*  
*Status: PRONTO PARA INICIAR*
