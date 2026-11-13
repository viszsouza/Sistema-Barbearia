</main>

<footer class="footer">
  <div class="wrap">
    <div class="footer__grid">
      <div>
        <span class="brand__mark">NAVALHA <span>&amp;</span> CIA</span>
        <p class="dim" style="margin-top:14px"><?= e((string) setting('business_tagline', '')) ?></p>
        <p class="dim"><?= e((string) setting('business_address', '')) ?></p>
      </div>

      <div>
        <h4>Navegue</h4>
        <ul>
          <li><a href="<?= e(PUBLIC_URL) ?>/servicos.php">Serviços e preços</a></li>
          <li><a href="<?= e(PUBLIC_URL) ?>/barbeiros.php">Nossos barbeiros</a></li>
          <li><a href="<?= e(PUBLIC_URL) ?>/agendamento.php">Agendar horário</a></li>
          <li><a href="<?= e(PUBLIC_URL) ?>/cliente.php">Meus horários</a></li>
          <li><a href="<?= e(PUBLIC_URL) ?>/contato.php">Contato e endereço</a></li>
        </ul>
      </div>

      <div>
        <h4>Fale com a gente</h4>
        <ul>
          <li><a href="tel:<?= e(only_digits((string) setting('business_phone', ''))) ?>"><?= e((string) setting('business_phone', '')) ?></a></li>
          <li><a href="mailto:<?= e((string) setting('business_email', '')) ?>"><?= e((string) setting('business_email', '')) ?></a></li>
          <?php if (setting('social_instagram')): ?>
            <li><a href="<?= e((string) setting('social_instagram')) ?>" rel="noopener" target="_blank">Instagram</a></li>
          <?php endif; ?>
          <?php if (setting('social_facebook')): ?>
            <li><a href="<?= e((string) setting('social_facebook')) ?>" rel="noopener" target="_blank">Facebook</a></li>
          <?php endif; ?>
        </ul>
      </div>
    </div>

    <div class="footer__base">
      <span>© <?= date('Y') ?> <?= e((string) setting('business_name', APP_NAME)) ?>. Todos os direitos reservados.</span>
      <a href="<?= e(ADMIN_URL) ?>/login.php">Acesso da equipe</a>
    </div>
  </div>
</footer>

<a class="fab" href="<?= e(whatsapp_link('Olá! Gostaria de falar sobre um horário.')) ?>" target="_blank" rel="noopener" aria-label="Falar no WhatsApp">
  <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91C21.96 6.45 17.5 2 12.04 2zm5.8 14.12c-.25.69-1.45 1.32-2 1.4-.51.08-1.16.11-1.87-.12-.43-.14-.99-.32-1.7-.63-2.99-1.29-4.94-4.3-5.09-4.5-.15-.2-1.22-1.62-1.22-3.1s.77-2.2 1.05-2.5c.27-.3.6-.37.8-.37.2 0 .4 0 .57.01.18.01.43-.07.67.51.25.6.85 2.08.92 2.23.08.15.13.33.02.53-.1.2-.15.32-.3.5-.15.17-.32.38-.45.51-.15.15-.3.31-.13.61.17.3.76 1.25 1.63 2.03 1.12 1 2.06 1.3 2.36 1.45.3.15.47.13.65-.08.17-.2.75-.87.95-1.17.2-.3.4-.25.67-.15.27.1 1.74.82 2.04.97.3.15.5.22.57.35.07.13.07.75-.18 1.44z"/></svg>
</a>

<script src="<?= e(ASSETS_URL) ?>/js/site.js" defer></script>
<?php if (!empty($pageScript)): ?><script src="<?= e($pageScript) ?>" defer></script><?php endif; ?>
</body>
</html>
