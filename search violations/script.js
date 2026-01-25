/*const searchInput = document.getElementById("searchInput");
const listItems = document.querySelectorAll("#violationList li");

searchInput.addEventListener("input", () => {
  const searchTerm = searchInput.value.toLowerCase();

  listItems.forEach(item => {
    const text = item.textContent.toLowerCase();
    item.style.display = text.includes(searchTerm) ? "list-item" : "none";
  });
});
*/

document.addEventListener("DOMContentLoaded", () => {
  const form = document.getElementById("handbookSearchForm");
  const input = document.getElementById("handbookSearchInput");
  const items = document.querySelectorAll("#handbookViolationList .violation-item");

  form.addEventListener("submit", (e) => {
    e.preventDefault();
    const term = input.value.trim().toLowerCase();

    items.forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(term) ? "list-item" : "none";
    });
  });

  // Allow Enter key to search without clicking the button
  input.addEventListener("keyup", (e) => {
    if (e.key === "Enter") {
      form.dispatchEvent(new Event("submit"));
    }
  });

  // Clicking a violation shows only that one
  document.querySelectorAll(".violation-link").forEach(link => {
    link.addEventListener("click", (e) => {
      e.preventDefault();
      const id = link.dataset.id;

      items.forEach(item => {
        item.style.display = item.querySelector(".violation-link").dataset.id === id ? "list-item" : "none";
      });
    });
  });
});
