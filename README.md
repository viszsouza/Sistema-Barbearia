# Navalha & Cia — Sistema de Gestão e Agendamento para Barbearia

Sistema completo em **PHP 8 + MySQL + HTML/CSS/JS puro** (sem frameworks, sem
build step), pronto para hospedagem compartilhada.

## Estrutura

```
/public      site público (home, serviços, barbeiros, agendamento, área do cliente)
/admin       painel administrativo
/api         endpoints JSON usados pelo site e pelo painel
/assets      CSS, JS e imagens
/includes    lógica compartilhada (auth, disponibilidade, notificações, layout)
/config      configuração e conexão com o banco
/database    schema.sql, seed.sql (base) e seed_demo.sql (opcional)
install.php  instalador — apagar depois de usar
```

## Instalação

1. Suba os arquivos para o servidor (ou configure um vhost local apontando
   para a raiz do projeto).
2. Crie um banco MySQL vazio e configure o acesso em `config/database.php`
   (ou pelas variáveis de ambiente `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`).
3. Ajuste `APP_URL` em `config/config.php` (ou a variável de ambiente
   `APP_URL`) para a URL real do site.
4. Acesse `/install.php` no navegador e preencha seus dados de administrador.
   A instalação é **limpa**: já vem com as *Configurações*, os *Serviços*
   (com categorias) e o *horário de funcionamento*; barbeiros, clientes e
   agendamentos começam vazios. Se quiser, marque a opção de demonstração
   para também carregar barbeiros, clientes e uma agenda de exemplo.
5. **Apague `install.php` do servidor** assim que a instalação terminar —
   deixá-lo publicado permite recriar o banco a qualquer momento.
6. Entre em `/admin/login.php` com o e-mail e senha criados no passo 4.

Se o seu servidor não usa Apache (sem `.htaccess`), ajuste o roteamento
equivalente no Nginx/IIS para bloquear `/config`, `/includes` e `/database`,
e redirecionar `/` para `/public/index.php`.

## Como a disponibilidade é calculada

Todo o cálculo de horários livres acontece no backend
(`includes/availability.php`), cruzando:

- horário de funcionamento da barbearia (`business_hours`)
- horário individual do barbeiro (`barber_hours`) — a interseção dos dois é
  o que vale
- duração do serviço escolhido
- agendamentos já existentes do barbeiro naquele dia
- bloqueios manuais (folgas, compromissos) — gerais ou por barbeiro
- feriados
- antecedência mínima e limite de dias futuros (Configurações)

Ao confirmar um agendamento, `create_appointment()` abre uma transação,
bloqueia a agenda do barbeiro daquele dia (`SELECT ... FOR UPDATE`),
**revalida** a disponibilidade e só então grava — isso evita que dois
clientes reservem o mesmo horário ao mesmo tempo.

## Permissões

Três papéis, definidos em `includes/auth.php`:

- **super_admin** — acesso total, incluindo Usuários
- **admin** — tudo, exceto gestão de usuários administrativos
- **receptionist** — agenda, agendamentos e clientes (sem editar cadastros
  de serviços/barbeiros/configurações)

## Notificações

- **E-mail**: implementado com `mail()` nativo do PHP — funciona na maioria
  das hospedagens compartilhadas sem configuração extra. Se preferir SMTP
  (recomendado para melhor entregabilidade), troque a função
  `notify_send_email()` em `includes/notifications.php` por uma chamada ao
  PHPMailer ou similar.
- **WhatsApp**: o contrato já existe (`notify_send_whatsapp()`, fila em
  `notifications`, toggle em Configurações), mas o driver está como stub.
  Para ativar, implemente a chamada à API escolhida (Meta Cloud API, Z-API,
  Evolution API etc.) dentro dessa função.
- **SMS**: mesma ideia — o canal já está previsto na tabela `notifications`.

## Pontos de extensão conhecidos

- Upload de imagens reais de barbeiros/serviços/galeria (hoje usam SVGs de
  placeholder em `assets/images/`) — os campos `photo` e `image` já existem
  no banco, falta a tela de upload no painel.
- Driver de WhatsApp (ver acima).
- Depoimentos são só leitura no site; um CRUD no painel pode ser adicionado
  seguindo o mesmo padrão de `admin/servicos.php`.

## Segurança implementada

PDO com prepared statements em 100% das queries, `password_hash` /
`password_verify` para logins, tokens CSRF em todos os formulários e
chamadas de API que alteram dados, sessão com cookies `httponly` +
`samesite`, regeneração periódica de ID de sessão, escape de saída (`e()`)
em todo HTML dinâmico, rate limiting simples em login e criação de
agendamentos, e cabeçalhos HTTP de proteção (`X-Content-Type-Options`,
`X-Frame-Options`).

## Observação sobre o ambiente de desenvolvimento

Este projeto foi escrito sem um interpretador PHP disponível para rodar
`php -l` linha a linha — a sintaxe foi revisada manualmente com cuidado,
mas vale rodar um `php -l` em cada arquivo (ou simplesmente abrir o site)
assim que subir para um ambiente com PHP antes de ir para produção.

## Dados iniciais

- `database/seed.sql` — instalado sempre: configurações, serviços/categorias e
  horário de funcionamento.
- `database/seed_demo.sql` — opcional (caixa no instalador): barbeiros,
  clientes, agendamentos, pagamentos, bloqueios, feriados e depoimentos de exemplo.

Depois de uma instalação limpa, o primeiro passo é cadastrar os barbeiros em
**Barbeiros** (marcando os serviços que cada um atende). Enquanto não houver
barbeiro ativo, o agendamento online mostra um aviso de indisponibilidade.
