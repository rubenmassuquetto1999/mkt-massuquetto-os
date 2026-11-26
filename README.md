# Massuquetto OS — Gestão de Agência & CRM (Produção)

Plataforma proprietária e de alta performance desenvolvida para a agência **MKT Massuquetto** (Ruben Massuquetto — Campo Largo, PR). Sistema integrado de ponta a ponta para operação comercial, técnica, de tráfego pago e financeira de agências de marketing digital.

---

## 🔒 Segurança de Produção & Credenciais Master

- **Usuário Admin / E-mail Master**: `rubenmassuquetto1999@gmail.com`
- **Senha Padrão Inicial**: `123456`
- **Blindagem de Produção**:
  - **Sessões Blindadas**: Proteção avançada contra *Session Fixation* (`session_regenerate_id(true)`).
  - **Anti-Enumeração**: Mensagens de erro padronizadas ("Credenciais inválidas") para evitar ataques de descoberta de contas.
  - **Recuperação Criptografada**: As senhas **nunca** são exibidas em texto na tela ao solicitar recuperação; o envio é feito exclusivamente via e-mail corporativo formatado com a marca MKT Massuquetto e auditado por IP e data no banco de dados (`db.json`).
- **Proteção do Banco de Dados**: Bloqueio de acesso direto via HTTP (`403 Forbidden`) e diretivas `.htaccess` robustas para servidores Apache.

---

## 🚀 Módulos do Sistema (100% PHP, CSS e JSON)

1. **Painel Geral (`index.php`)**:
   - 4 Cartões Indicadores em tempo real: Faturamento Total, Lucro Líquido (já descontando pagamentos de freelancers), Leads Ativos e Projetos em Andamento.
   - Listagem rápida de interessados e alertas automáticos de cobranças vencidas / próximas a vencer e saldo baixo em anúncios.

2. **CRM de Clientes e Leads (`leads.php`)**:
   - Cadastro e edição de leads: Nome, Empresa, WhatsApp, Serviço desejado, Nível de Urgência (*Alta, Média, Baixa*), Valor Estimado e Observações.
   - Filtro por etapa (*Novo, Qualificado, Proposta Enviada, Fechado, Perdido*).
   - Botão de disparo direto para conversa no WhatsApp do cliente.
   - **Conversão em 1 Clique**: Transforma lead em Projeto/Client e alimenta automaticamente o financeiro.

3. **Esteira de Projetos e Freelancers (`projetos.php`)**:
   - Gestão de entregas em Lista ou **Esteira Kanban** (*Briefing, Copywriting & UI/UX, Desenvolvimento / Execução, Velocidade & Traqueamento, Revisão / Aprovação, Entregue*).
   - Atribuição de freelancer responsável, valor cobrado, valor pago ao freela e cálculo automático do **lucro líquido**.
   - Monitoramento de **Uptime / Velocidade** da página em homologação.

4. **Catálogo Comercial & Escopo de Serviços (`servicos.php`)**:
   - Tabela comercial dos 5 serviços principais (*Sites & Landing Pages, Gestão de Tráfego Pago, SEO Local / GMN, Branding & Visual, Social Media & Content*).
   - Precificação editável (Preço Base, Custo de Freelancer, Margem de Lucro em R$ e %).
   - Escopo técnico detalhado com pilares de entrega e **Gerador de Texto de Proposta para WhatsApp**.

5. **Tráfego & Performance (`performance.php`)**:
   - Acompanhamento de campanhas (Google Ads e Meta Ads).
   - Controle de orçamento teto, saldo atual, investido, leads gerados, vendas fechadas e receita gerada (ROI / ROAS).
   - **Diário de Otimizações**: Log de melhorias técnicas realizadas pelos gestores.

6. **Financeiro: Setup e Mensalidades (`financeiro.php`)**:
   - Controle de cobranças únicas (Setup) e recorrentes (Mensalidades).
   - Status: *Pago, Pendente, Atrasado*.
   - **Cálculo Automático de Encargos**: Multa de 2% + juros de 1% ao mês para faturas em atraso.

7. **Gerador de Contrato A4 (`contrato.php`)**:
   - Contrato jurídico completo de prestação de serviços de Marketing Digital.
   - Emissão e download direto de **PDF em formato A4** gerado em PHP puro (sem bibliotecas externas) com quebras de página controladas e assinaturas alinhadas.

8. **Portal do Cliente (`portal.php`)**:
   - Área externa por link seguro (`?cliente=...&token=...`) para visualização de resultados de tráfego, aprovação de layouts e extrato financeiro.

---
Desenvolvido por **Ruben Massuquetto** • MKT Massuquetto (Ambiente de Produção)
