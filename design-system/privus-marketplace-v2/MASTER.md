# Design System Master File - Privus Marketplace V2

> **LOGIC:** When building a specific page, first check `design-system/privus-marketplace-v2/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** Privus Marketplace V2
**Theme:** Glassmorphism Tech - Modern SaaS ERP
**Generated:** 2026-03-05
**Category:** Enterprise SaaS Dashboard

---

## Philosophy

O novo design system **Glassmorphism Tech** traz:
- **Profundidade visual** através de glassmorphism sutil
- **Modernidade** com cantos arredondados, sombras suaves e gradients
- **Profissionalismo** com paleta de cores sóbria (Slate/Ciano)
- **Tecnologia** com acentos em ciano e efeitos de brilho
- **Acessibilidade** mantendo contraste 4.5:1+ em todos os elementos

---

## Global Rules

### Color Palette

#### Primary Colors (Slate - Base Profissional)
| Role | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Background | `#0F172A` | `slate-900` | Dark mode principal |
| Surface | `#1E293B` | `slate-800` | Cards, painéis |
| Elevated | `#334155` | `slate-700` | Hover states, dropdowns |
| Border | `#475569` | `slate-600` | Bordas sutis |
| Muted | `#94A3B8` | `slate-400` | Texto secundário |
| Text | `#F1F5F9` | `slate-100` | Texto principal |

#### Accent Colors (Ciano - Tech Feel)
| Role | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Primary | `#06B6D4` | `cyan-500` | Links, botões primários |
| Primary Hover | `#22D3EE` | `cyan-400` | Hover em elementos primários |
| Primary Glow | `#06B6D433` | `cyan-500/20` | Efeitos de brilho |
| Secondary | `#8B5CF6` | `violet-500` | Acentos secundários |

#### Semantic Colors
| Role | Hex | Tailwind | Usage |
|------|-----|----------|-------|
| Success | `#10B981` | `emerald-500` | Sucesso, confirmações |
| Warning | `#F59E0B` | `amber-500` | Alertas, atenção |
| Error | `#EF4444` | `red-500` | Erros, deleções |
| Info | `#3B82F6` | `blue-500` | Informações |

### Typography

- **Heading Font:** Inter (moderno, legível, profissional)
- **Body Font:** Inter (consistência, melhor legibilidade)
- **Mono Font:** JetBrains Mono (código, dados técnicos)
- **Mood:** modern, clean, tech, enterprise, professional

**Google Fonts:**
```css
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap');
```

**Type Scale:**
| Token | Size | Line Height | Weight | Usage |
|-------|------|-------------|--------|-------|
| `text-xs` | 12px | 16px | 400 | Labels, badges |
| `text-sm` | 14px | 20px | 400 | Texto secundário |
| `text-base` | 16px | 24px | 400 | Body text |
| `text-lg` | 18px | 28px | 500 | Subtítulos |
| `text-xl` | 20px | 28px | 600 | Títulos pequenos |
| `text-2xl` | 24px | 32px | 700 | Títulos de seção |
| `text-3xl` | 30px | 36px | 700 | Títulos de página |

### Spacing System

| Token | Value | Usage |
|-------|-------|-------|
| `space-1` | 4px | Micro gaps |
| `space-2` | 8px | Icon gaps |
| `space-3` | 12px | Compact padding |
| `space-4` | 16px | Standard padding |
| `space-5` | 20px | Form gaps |
| `space-6` | 24px | Section padding |
| `space-8` | 32px | Large gaps |
| `space-10` | 40px | Section margins |
| `space-12` | 48px | Major sections |

### Border Radius

| Token | Value | Usage |
|-------|-------|-------|
| `rounded-sm` | 4px | Badges, tags |
| `rounded` | 6px | Inputs, buttons small |
| `rounded-lg` | 8px | Cards default |
| `rounded-xl` | 12px | Modais, featured cards |
| `rounded-2xl` | 16px | Large containers |
| `rounded-full` | 9999px | Avatars, pills |

### Shadow System (Glassmorphism)

| Level | Value | Usage |
|-------|-------|-------|
| `shadow-sm` | `0 1px 2px 0 rgb(0 0 0 / 0.3)` | Subtle lift |
| `shadow` | `0 4px 6px -1px rgb(0 0 0 / 0.4), 0 2px 4px -2px rgb(0 0 0 / 0.4)` | Cards |
| `shadow-lg` | `0 10px 15px -3px rgb(0 0 0 / 0.4), 0 4px 6px -4px rgb(0 0 0 / 0.4)` | Elevated cards |
| `shadow-cyan` | `0 0 20px -5px rgba(6, 182, 212, 0.3)` | Glow effect |
| `shadow-glass` | `0 8px 32px 0 rgba(0, 0, 0, 0.37)` | Glass panels |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: linear-gradient(135deg, #06B6D4 0%, #0891B2 100%);
  color: white;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  transition: all 200ms ease;
  box-shadow: 0 4px 14px 0 rgba(6, 182, 212, 0.35);
  border: 1px solid rgba(255, 255, 255, 0.1);
}

.btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 6px 20px 0 rgba(6, 182, 212, 0.45);
  background: linear-gradient(135deg, #22D3EE 0%, #06B6D4 100%);
}

/* Secondary Button */
.btn-secondary {
  background: rgba(30, 41, 59, 0.8);
  color: #F1F5F9;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  border: 1px solid rgba(71, 85, 105, 0.8);
  backdrop-filter: blur(8px);
  transition: all 200ms ease;
}

.btn-secondary:hover {
  background: rgba(51, 65, 85, 0.9);
  border-color: rgba(148, 163, 184, 0.5);
}

/* Ghost Button */
.btn-ghost {
  background: transparent;
  color: #94A3B8;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  transition: all 200ms ease;
}

.btn-ghost:hover {
  color: #F1F5F9;
  background: rgba(255, 255, 255, 0.05);
}

/* Danger Button */
.btn-danger {
  background: rgba(239, 68, 68, 0.15);
  color: #EF4444;
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 500;
  font-size: 14px;
  border: 1px solid rgba(239, 68, 68, 0.3);
  transition: all 200ms ease;
}

.btn-danger:hover {
  background: rgba(239, 68, 68, 0.25);
  border-color: rgba(239, 68, 68, 0.5);
}
```

### Cards (Glassmorphism)

```css
.card {
  background: rgba(30, 41, 59, 0.7);
  backdrop-filter: blur(12px);
  -webkit-backdrop-filter: blur(12px);
  border: 1px solid rgba(71, 85, 105, 0.4);
  border-radius: 12px;
  padding: 24px;
  transition: all 300ms ease;
}

.card:hover {
  border-color: rgba(6, 182, 212, 0.3);
  box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.37), 0 0 20px -5px rgba(6, 182, 212, 0.15);
  transform: translateY(-2px);
}

.card-header {
  border-bottom: 1px solid rgba(71, 85, 105, 0.4);
  padding-bottom: 16px;
  margin-bottom: 16px;
}

.card-title {
  font-size: 16px;
  font-weight: 600;
  color: #F1F5F9;
}

.card-subtitle {
  font-size: 13px;
  color: #94A3B8;
  margin-top: 4px;
}
```

### Inputs

```css
.input {
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 8px;
  padding: 12px 16px;
  font-size: 14px;
  color: #F1F5F9;
  transition: all 200ms ease;
  width: 100%;
}

.input::placeholder {
  color: #64748B;
}

.input:focus {
  outline: none;
  border-color: #06B6D4;
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
}

.input-group {
  display: flex;
  align-items: center;
  background: rgba(15, 23, 42, 0.8);
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 8px;
  padding: 0 12px;
  transition: all 200ms ease;
}

.input-group:focus-within {
  border-color: #06B6D4;
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15);
}

.input-group .icon {
  color: #64748B;
  margin-right: 12px;
}

.input-group input {
  background: transparent;
  border: none;
  padding: 12px 0;
  color: #F1F5F9;
  flex: 1;
}

.input-group input:focus {
  outline: none;
  box-shadow: none;
}
```

### Sidebar (Modern Glass)

```css
.sidebar {
  background: rgba(15, 23, 42, 0.95);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-right: 1px solid rgba(71, 85, 105, 0.3);
  width: 280px;
  transition: all 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

.sidebar.collapsed {
  width: 72px;
}

.sidebar-logo {
  height: 64px;
  display: flex;
  align-items: center;
  padding: 0 20px;
  border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

.sidebar-logo-icon {
  width: 36px;
  height: 36px;
  background: linear-gradient(135deg, #06B6D4 0%, #8B5CF6 100%);
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 700;
  color: white;
  font-size: 14px;
  box-shadow: 0 4px 14px rgba(6, 182, 212, 0.3);
}

.nav-section {
  padding: 0 12px;
  margin-bottom: 8px;
}

.nav-section-title {
  font-size: 11px;
  font-weight: 600;
  color: #64748B;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 16px 12px 8px;
}

.nav-link {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 12px;
  border-radius: 8px;
  color: #94A3B8;
  font-size: 14px;
  font-weight: 500;
  transition: all 200ms ease;
  margin-bottom: 2px;
}

.nav-link:hover {
  background: rgba(255, 255, 255, 0.05);
  color: #F1F5F9;
}

.nav-link.active {
  background: linear-gradient(90deg, rgba(6, 182, 212, 0.15) 0%, rgba(6, 182, 212, 0.05) 100%);
  color: #22D3EE;
  border-left: 2px solid #06B6D4;
}

.nav-link .icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}
```

### Topbar

```css
.topbar {
  height: 64px;
  background: rgba(15, 23, 42, 0.8);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  border-bottom: 1px solid rgba(71, 85, 105, 0.3);
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0 24px;
  position: sticky;
  top: 0;
  z-index: 30;
}

.search-bar {
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(71, 85, 105, 0.4);
  border-radius: 10px;
  padding: 10px 16px;
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 320px;
  transition: all 200ms ease;
}

.search-bar:focus-within {
  border-color: rgba(6, 182, 212, 0.5);
  box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.1);
}

.search-bar input {
  background: transparent;
  border: none;
  color: #F1F5F9;
  font-size: 14px;
  flex: 1;
}

.search-bar input::placeholder {
  color: #64748B;
}

.user-menu {
  display: flex;
  align-items: center;
  gap: 12px;
}

.icon-button {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #94A3B8;
  background: rgba(30, 41, 59, 0.6);
  border: 1px solid rgba(71, 85, 105, 0.4);
  transition: all 200ms ease;
}

.icon-button:hover {
  background: rgba(51, 65, 85, 0.8);
  color: #F1F5F9;
  border-color: rgba(6, 182, 212, 0.4);
}

.user-avatar {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #06B6D4 0%, #8B5CF6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  color: white;
  font-size: 13px;
  cursor: pointer;
  box-shadow: 0 2px 8px rgba(6, 182, 212, 0.3);
}
```

### Tables (Data Grid)

```css
.data-table {
  width: 100%;
  border-collapse: separate;
  border-spacing: 0;
}

.data-table th {
  background: rgba(30, 41, 59, 0.6);
  color: #94A3B8;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  padding: 14px 20px;
  text-align: left;
  border-bottom: 1px solid rgba(71, 85, 105, 0.4);
}

.data-table td {
  padding: 16px 20px;
  color: #F1F5F9;
  font-size: 14px;
  border-bottom: 1px solid rgba(71, 85, 105, 0.3);
}

.data-table tr:hover td {
  background: rgba(255, 255, 255, 0.02);
}

.data-table tr:last-child td {
  border-bottom: none;
}
```

### Badges & Status

```css
.badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 500;
}

.badge-success {
  background: rgba(16, 185, 129, 0.15);
  color: #34D399;
  border: 1px solid rgba(16, 185, 129, 0.3);
}

.badge-warning {
  background: rgba(245, 158, 11, 0.15);
  color: #FBBF24;
  border: 1px solid rgba(245, 158, 11, 0.3);
}

.badge-error {
  background: rgba(239, 68, 68, 0.15);
  color: #F87171;
  border: 1px solid rgba(239, 68, 68, 0.3);
}

.badge-info {
  background: rgba(6, 182, 212, 0.15);
  color: #22D3EE;
  border: 1px solid rgba(6, 182, 212, 0.3);
}

.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
}

.status-dot.online {
  background: #10B981;
  box-shadow: 0 0 8px #10B981;
}

.status-dot.offline {
  background: #EF4444;
}

.status-dot.pending {
  background: #F59E0B;
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(4px);
  position: fixed;
  inset: 0;
  z-index: 50;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.modal {
  background: rgba(30, 41, 59, 0.95);
  backdrop-filter: blur(20px);
  border: 1px solid rgba(71, 85, 105, 0.5);
  border-radius: 16px;
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  max-width: 480px;
  width: 100%;
  overflow: hidden;
}

.modal-header {
  padding: 20px 24px;
  border-bottom: 1px solid rgba(71, 85, 105, 0.4);
}

.modal-title {
  font-size: 18px;
  font-weight: 600;
  color: #F1F5F9;
}

.modal-body {
  padding: 24px;
}

.modal-footer {
  padding: 16px 24px;
  border-top: 1px solid rgba(71, 85, 105, 0.4);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}
```

---

## Style Guidelines

**Style:** Glassmorphism Tech Dashboard

**Keywords:** glass effect, frosted glass, modern gradients, soft shadows, tech enterprise, dark mode first, cyan accents, floating UI, depth layers

**Best For:** SaaS dashboards, enterprise software, developer tools, fintech, data platforms, modern ERP systems

**Key Effects:**
- Glassmorphism backdrop blur em cards e painéis
- Gradients sutis em elementos primários
- Glow effects em hover (cyan shadow)
- Floating navbar com blur
- Smooth transitions em todas as interações (200-300ms)
- Hover states com elevação e glow

---

## Animation Guidelines

### Durations
| Type | Duration | Usage |
|------|----------|-------|
| Micro | 150ms | Hover em botões, links |
| Standard | 200ms | Cards, dropdowns |
| Emphasis | 300ms | Modais, sidebar toggle |
| Complex | 400ms | Page transitions |

### Easing
| Name | Value | Usage |
|------|-------|-------|
| `ease-smooth` | `cubic-bezier(0.4, 0, 0.2, 1)` | Most animations |
| `ease-bounce` | `cubic-bezier(0.34, 1.56, 0.64, 1)` | Playful elements |
| `ease-in-out` | `cubic-bezier(0.4, 0, 0.6, 1)` | Symmetric animations |

### Transitions
```css
/* Standard element transition */
.element {
  transition: all 200ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* Card hover with lift */
.card {
  transition: transform 300ms cubic-bezier(0.4, 0, 0.2, 1),
              box-shadow 300ms cubic-bezier(0.4, 0, 0.2, 1),
              border-color 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

/* Sidebar collapse */
.sidebar {
  transition: width 300ms cubic-bezier(0.4, 0, 0.2, 1);
}
```

---

## Anti-Patterns (Do NOT Use)

- ❌ **Solid colors sem transparência** — Sempre use rgba() com alpha
- ❌ **Borders fortes** — Mantenha bordas sutis e semitransparentes
- ❌ **Shadows pesadas** — Use sombras suaves, nunca `box-shadow: 0 10px 20px black`
- ❌ **Cores vibrantes demais** — Mantenha saturação controlada
- ❌ **Glassmorphism excessivo** — Não aplique blur em tudo, apenas em elementos flutuantes
- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide)
- ❌ **Missing cursor:pointer** — Todos os elementos clicáveis precisam
- ❌ **Low contrast text** — Mantenha 4.5:1 mínimo
- ❌ **Instant state changes** — Sempre use transições

---

## Pre-Delivery Checklist

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons outline 24px)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (200-300ms)
- [ ] Glassmorphism aplicado corretamente (backdrop-blur)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] Cyan glow effects em elementos primários
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbar
- [ ] Gradients suaves em botões primários
