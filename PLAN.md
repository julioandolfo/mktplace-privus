# Sistema de Metas & Bonificação — Expedição

## Contexto

O sistema de expedição já rastreia qual operador executou cada ação (conferência, embalagem, despacho) via `OrderTimeline.data.operator_id`. Agora queremos:

1. **Metas diárias** — calcular quantos pedidos precisam ser processados por dia para despachar tudo dentro do prazo
2. **Pontos por produto** — cada item embalado/conferido vale pontos (configurável por produto)
3. **Bonificação em R$** — converter pontos em valor monetário e distribuir entre operadores

---

## Arquitetura Proposta

### Tabelas Novas

#### 1. `expedition_bonus_config` (configuração global por empresa)
| Coluna | Tipo | Descrição |
|--------|------|-----------|
| company_id | FK | Empresa |
| points_value_cents | integer | Valor de cada ponto em centavos (ex: 10 = R$0,10/ponto) |
| default_product_points | integer | Pontos padrão por unidade de produto (ex: 1) |
| split_mode | enum | Como dividir entre operadores: `equal` (igual), `weighted` (por etapa), `by_role` (por função) |
| packing_weight | integer | Peso da etapa conferência/embalagem (ex: 60%) |
| shipping_weight | integer | Peso da etapa despacho (ex: 40%) |
| min_daily_orders_goal | integer | Meta mínima manual de pedidos/dia (0 = automático) |
| deadline_buffer_days | integer | Dias de folga antes do prazo real (ex: 1 = processar 1 dia antes) |
| is_active | boolean | Liga/desliga o sistema |

#### 2. `products.expedition_points` (coluna nova na tabela existente)
| Coluna | Tipo | Descrição |
|--------|------|-----------|
| expedition_points | smallint, nullable | Pontos customizados. NULL = usa `default_product_points` da config |

#### 3. `expedition_points_log` (registro de pontos ganhos)
| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | PK | |
| company_id | FK | |
| operator_id | FK → expedition_operators | Operador que ganhou |
| order_id | FK → orders | Pedido relacionado |
| order_item_id | FK → order_items, nullable | Item específico (null = ação geral do pedido) |
| event_type | string | `packing`, `shipping` |
| points | integer | Pontos atribuídos |
| reference_date | date | Data de referência (para agrupar por dia/mês) |
| created_at | timestamp | |

#### 4. `expedition_goals` (metas mensais calculadas/manuais)
| Coluna | Tipo | Descrição |
|--------|------|-----------|
| id | PK | |
| company_id | FK | |
| month | date | Mês de referência (primeiro dia) |
| total_pending_orders | integer | Total de pedidos pendentes no início do mês |
| working_days | integer | Dias úteis no mês |
| daily_order_goal | integer | Meta de pedidos/dia calculada |
| total_points_goal | integer | Meta total de pontos no mês |
| is_locked | boolean | Travar meta (não recalcular automaticamente) |
| notes | text | Observações |

---

## Fluxo de Funcionamento

### A) Cálculo da Meta Diária (automático)

```
1. Pegar todos os pedidos pendentes de expedição (pipeline_status em ready_to_ship, packing, packed)
2. Para cada pedido, verificar prazo (meta['ml_shipping_deadline'] ou shipped_at esperado)
3. Agrupar por dia de vencimento
4. Calcular: pedidos_por_dia = pedidos_pendentes / dias_uteis_restantes_no_mes
5. Aplicar buffer (deadline_buffer_days) → antecipar X dias
6. Resultado: "Hoje vocês precisam processar N pedidos para ficar em dia"
```

### B) Atribuição de Pontos (automático ao confirmar ação)

```
Quando operador confirma embalagem (packing_checked):
  Para cada item do pedido:
    pontos = produto.expedition_points ?? config.default_product_points
    pontos_total += pontos * quantidade_confirmada
  Registrar em expedition_points_log (event_type = 'packing', operator_id, pontos_total)

Quando operador marca como enviado (shipped):
  pontos = soma dos pontos dos itens do pedido (mesma lógica)
  Registrar em expedition_points_log (event_type = 'shipping', operator_id, pontos_total)
```

### C) Distribuição da Bonificação

```
Ao fechar o mês (ou consultar relatório):
  1. Somar pontos por operador no período
  2. Calcular valor: total_pontos × points_value_cents / 100
  3. Exibir ranking com pontos e R$ por operador
```

**Modos de split (quando 2+ operadores participam do mesmo pedido):**
- `equal`: divide pontos igualmente entre todos que participaram
- `weighted`: conferente ganha X%, expedidor ganha Y% (configurável)
- `by_role`: cada etapa (packing/shipping) ganha 100% dos seus pontos, sem divisão

> **Recomendação:** usar `by_role` como padrão — cada operador ganha pontos pela etapa que executou. É o mais justo e simples.

---

## Telas / UI

### 1. Configurações > Bonificação Expedição (nova página)
- Valor do ponto (R$)
- Pontos padrão por produto
- Modo de distribuição
- Pesos por etapa (se weighted)
- Buffer de dias antes do prazo
- Toggle ativo/inativo

### 2. Dashboard de Metas (widget no topo do Expedition Board)
```
┌─────────────────────────────────────────────────────────┐
│  📦 Meta do Dia: 45 pedidos   |  ✅ Processados: 28    │
│  ████████████████░░░░░  62%   |  Faltam: 17 pedidos    │
│                                                         │
│  📅 Mês: 340/520 pedidos  |  🏆 João: 1.240 pts       │
│  ███████████████░░░░░░  65%  |  🥈 Maria: 980 pts     │
└─────────────────────────────────────────────────────────┘
```

### 3. Relatório Mensal de Bonificação (nova página)
- Tabela: Operador | Pedidos | Itens | Pontos | R$
- Filtro por mês
- Gráfico de evolução diária
- Exportar CSV
- Detalhamento por operador (quais pedidos, quais itens)

### 4. Produto — Campo "Pontos de Expedição"
- No cadastro do produto, campo opcional `expedition_points`
- Se vazio, usa o padrão global
- Produtos complexos/grandes podem valer mais pontos

---

## Ordem de Implementação

### Fase 1 — Infraestrutura (migrations + models + config)
1. Migration: `expedition_bonus_config`
2. Migration: adicionar `expedition_points` em `products`
3. Migration: `expedition_points_log`
4. Migration: `expedition_goals`
5. Models: `ExpeditionBonusConfig`, `ExpeditionPointsLog`, `ExpeditionGoal`
6. Página de configuração em Settings

### Fase 2 — Pontuação Automática
7. Service `ExpeditionBonusService` com métodos:
   - `awardPackingPoints(Order, operatorId)`
   - `awardShippingPoints(Order, operatorId)`
   - `calculateDailyGoal(companyId)`
8. Integrar no `ExpeditionBoard` (hooks em `confirmPacking` e `markShipped`)
9. Campo `expedition_points` no form de produto

### Fase 3 — Dashboard de Metas
10. Widget no topo do Expedition Board (meta do dia + progresso)
11. Mini ranking lateral dos operadores

### Fase 4 — Relatório Completo
12. Página `/expedition/bonuses` com relatório mensal
13. Detalhamento por operador
14. Exportar CSV

---

## Perguntas para Validar

1. O modo `by_role` (cada um ganha pontos pela sua etapa) faz sentido como padrão? Ou preferem dividir sempre entre todos?
2. Metas devem ser calculadas automaticamente (baseado em pedidos pendentes / dias úteis) ou setadas manualmente?
3. O relatório deve ter fechamento mensal (travar valores) ou ser sempre dinâmico?
4. Produtos sem cadastro de pontos devem valer 1 ponto por unidade como padrão?
