document.addEventListener('DOMContentLoaded', () => {
  const cards = document.querySelectorAll('.service-card, .paper-box, .hero-card');

  cards.forEach((card) => {
    card.addEventListener('mouseenter', () => {
      card.style.transform = 'translateY(-4px)';
      card.style.transition = 'transform 0.2s ease';
    });

    card.addEventListener('mouseleave', () => {
      card.style.transform = 'translateY(0)';
    });
  });
});
