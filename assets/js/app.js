document.querySelectorAll('input[type="password"]').forEach(function (input, index) {
    const wrapper = document.createElement("div");
    wrapper.className = "password-field";
    input.parentNode.insertBefore(wrapper, input);
    wrapper.appendChild(input);

    if (!input.id) {
        input.id = "password-field-" + (index + 1);
    }

    const toggle = document.createElement("button");
    toggle.className = "password-toggle";
    toggle.type = "button";
    toggle.textContent = "Show";
    toggle.setAttribute("aria-label", "Show password");
    toggle.setAttribute("aria-controls", input.id);
    toggle.setAttribute("aria-pressed", "false");
    toggle.addEventListener("click", function () {
        const isVisible = input.type === "text";
        input.type = isVisible ? "password" : "text";
        toggle.textContent = isVisible ? "Show" : "Hide";
        toggle.setAttribute("aria-label", isVisible ? "Show password" : "Hide password");
        toggle.setAttribute("aria-pressed", String(!isVisible));
    });
    wrapper.appendChild(toggle);
});
