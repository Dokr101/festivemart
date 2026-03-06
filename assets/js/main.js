document.addEventListener("DOMContentLoaded", () => {
  /* ── Mobile Nav ──── */
  const hamburger = document.querySelector(".hamburger");
  const navLinks = document.querySelector(".nav-links");
  if (hamburger && navLinks) {
    hamburger.addEventListener("click", () => {
      navLinks.classList.toggle("open");
      hamburger.children[0].style.transform = navLinks.classList.contains(
        "open",
      )
        ? "translateY(7px) rotate(45deg)"
        : "none";
      hamburger.children[1].style.opacity = navLinks.classList.contains("open")
        ? "0"
        : "1";
      hamburger.children[2].style.transform = navLinks.classList.contains(
        "open",
      )
        ? "translateY(-7px) rotate(-45deg)"
        : "none";
    });
  }

  /* ── Navbar Scroll Effect ──── */
  const navbar = document.querySelector(".navbar");
  if (navbar) {
    window.addEventListener("scroll", () => {
      if (window.scrollY > 50) navbar.classList.add("scrolled");
      else navbar.classList.remove("scrolled");
    });
  }

  /* ── Countdown Timer (Live) ──── */
  const numDays = document.getElementById("cd-days");
  const numHours = document.getElementById("cd-hours");
  const numMins = document.getElementById("cd-mins");
  const numSecs = document.getElementById("cd-secs");

  if (numDays && numDays.dataset.target) {
    const targetDate = new Date(numDays.dataset.target).getTime();

    const updateTimer = () => {
      const now = new Date().getTime();
      const distance = targetDate - now;

      if (distance < 0) {
        if (numDays) numDays.innerText = "00";
        if (numHours) numHours.innerText = "00";
        if (numMins) numMins.innerText = "00";
        if (numSecs) numSecs.innerText = "00";
        return;
      }

      const days = Math.floor(distance / (1000 * 60 * 60 * 24));
      const hours = Math.floor(
        (distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60),
      );
      const mins = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
      const secs = Math.floor((distance % (1000 * 60)) / 1000);

      if (numDays.innerText != pad(days)) {
        numDays.innerText = pad(days);
        triggerTick(numDays);
      }
      if (numHours.innerText != pad(hours)) {
        numHours.innerText = pad(hours);
        triggerTick(numHours);
      }
      if (numMins.innerText != pad(mins)) {
        numMins.innerText = pad(mins);
        triggerTick(numMins);
      }
      if (numSecs.innerText != pad(secs)) {
        numSecs.innerText = pad(secs);
        triggerTick(numSecs);
      }
    };

    const pad = (n) => (n < 10 ? "0" + n : n);
    const triggerTick = (el) => {
      el.classList.remove("tick");
      void el.offsetWidth; // trigger reflow
      el.classList.add("tick");
    };

    updateTimer();
    setInterval(updateTimer, 1000);
  }

  /* ── Generate Hero Particles ──── */
  const particleContainer = document.getElementById("hero-particles");
  if (particleContainer && particleContainer.dataset.type) {
    const type = particleContainer.dataset.type;
    const color1 = particleContainer.dataset.c1;
    const color2 = particleContainer.dataset.c2;
    const count = window.innerWidth > 768 ? 40 : 20;

    for (let i = 0; i < count; i++) {
      const p = document.createElement("div");
      p.className = `particle p-${type}`;

      // Randomize position, size, delay, and animation duration
      const left = Math.random() * 100;
      const size =
        Math.random() * (type === "diyo" ? 10 : 15) +
        (type === "diyo" ? 15 : 5);
      const delay = Math.random() * 10;
      const duration = Math.random() * 10 + 10; // 10-20s float
      const drift = Math.random() * 100 - 50 + "px"; // horizontal drift

      p.style.left = `${left}%`;
      p.style.width = `${size}px`;
      p.style.height = `${size}px`;
      p.style.animationDelay = `${delay}s`;
      p.style.animationDuration = `${duration}s`;
      p.style.setProperty("--drift", drift);

      // Assign color based on particle type
      if (type === "diyo") {
        p.innerHTML = "🪔";
      } else if (type !== "snow" && type !== "star") {
        p.style.backgroundColor = Math.random() > 0.5 ? color1 : color2;
      } else if (type === "star") {
        p.style.backgroundColor = color2;
      }

      particleContainer.appendChild(p);
    }
  }

  /* ── Password Eye Icon Toggle ──── */
  document.querySelectorAll(".toggle-password").forEach((icon) => {
    icon.addEventListener("click", function () {
      const input = document.getElementById(this.dataset.target);
      if (input) {
        if (input.type === "password") {
          input.type = "text";
          this.innerHTML = "👁️‍🗨️"; // Use a different icon for open eye
        } else {
          input.type = "password";
          this.innerHTML = "👁️";
        }
      }
    });
  });

  /* ── Cart Bounce Animation ──── */
  const addToCartBtns = document.querySelectorAll(".add-to-cart-btn");
  addToCartBtns.forEach((btn) => {
    btn.addEventListener("click", function (e) {
      // NOTE: In the real implementation, this would trigger an AJAX request
      // For now, it's just the visual feedback
      this.classList.remove("added");
      void this.offsetWidth;
      this.classList.add("added");

      // Find and bounce cart icon in navbar
      const cartBadge = document.querySelector(".cart-count");
      if (cartBadge) {
        cartBadge.style.animation = "none";
        void cartBadge.offsetWidth;
        cartBadge.style.animation =
          "cartBounce 0.5s cubic-bezier(0.36,0.07,0.19,0.97) both";
        // Demo increment
        let count = parseInt(cartBadge.innerText) || 0;
        // cartBadge.innerText = count + 1; // Handled by PHP form reload for now
      }
    });
  });

  /* ── Quantity Controls ──── */
  document.querySelectorAll(".qty-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      const input = this.parentElement.querySelector(".qty-input");
      const targetDisplay = document.getElementById(this.dataset.display);
      if (!input) return;

      let val = parseInt(input.value);
      if (this.classList.contains("minus") && val > 1) {
        input.value = val - 1;
      } else if (
        this.classList.contains("plus") &&
        val < parseInt(input.max || 99)
      ) {
        input.value = val + 1;
      }

      if (targetDisplay) targetDisplay.innerText = input.value;

      // If inside a form auto-submit (cart page)
      if (this.dataset.autosubmit) {
        this.closest("form").submit();
      }
    });
  });

  /* ── Account Tabs ──── */
  const tabLinks = document.querySelectorAll(".account-nav-link");
  const tabPanels = document.querySelectorAll(".account-panel");
  tabLinks.forEach((link) => {
    link.addEventListener("click", function () {
      const target = this.dataset.target;

      tabLinks.forEach((l) => l.classList.remove("active"));
      this.classList.add("active");

      tabPanels.forEach((p) => {
        p.classList.remove("active");
        if (p.id === target) p.classList.add("active");
      });
    });
  });
});
