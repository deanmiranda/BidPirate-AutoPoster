
// Single Auction Post Accordion JS 
document.addEventListener("DOMContentLoaded", () => {
  const toggles = document.querySelectorAll(".accordion-toggle");

  toggles.forEach(toggle => {
    const section = toggle.closest(".accordion-section");
    const content = section.querySelector(".section-content");

    // Start closed
    content.style.display = "none";

    toggle.addEventListener("click", () => {
      const isOpen = toggle.classList.toggle("open");
      content.style.display = isOpen ? "flex" : "none";
    });
  });
});
