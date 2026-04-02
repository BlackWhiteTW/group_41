const mockForms = [
  {
    id: 1,
    title: "迎新活動滿意度調查",
    author: "學生會",
    submissions: 123,
    createdAt: "2026-03-14",
    type: "public",
    description: "協助我們優化迎新流程，填答約 2 分鐘。"
  },
  {
    id: 2,
    title: "幹部培訓課程回饋",
    author: "社團評鑑中心",
    submissions: 88,
    createdAt: "2026-03-18",
    type: "club_only",
    description: "針對課程內容、講師安排與時段規劃提供建議。"
  },
  {
    id: 3,
    title: "社課設備需求調查",
    author: "熱音社",
    submissions: 57,
    createdAt: "2026-03-20",
    type: "public",
    description: "統計社課設備缺口，作為下學期預算依據。"
  }
];

function byId(id) {
  return document.getElementById(id);
}

function renderFormCards() {
  const root = byId("public-form-list");
  if (!root) {
    return;
  }

  root.innerHTML = mockForms
    .filter((f) => f.type === "public")
    .map(
      (f, idx) => `
      <article class="panel form-preview fade-up" style="animation-delay:${idx * 80}ms;">
        <span class="pill">公開表單</span>
        <h3>${f.title}</h3>
        <p class="muted">${f.description}</p>
        <p class="meta">出題者：${f.author} ・ 填寫數：${f.submissions} ・ 建立日：${f.createdAt}</p>
        <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
          <a class="btn btn-primary" href="forms-view.html">填寫表單</a>
          <a class="btn btn-ghost" href="dashboard.html">查看統計</a>
        </div>
      </article>
    `
    )
    .join("");
}

function setupSimpleValidation(formId) {
  const form = byId(formId);
  if (!form) {
    return;
  }

  form.addEventListener("submit", (event) => {
    const requiredFields = form.querySelectorAll("[required]");
    const checkedRadioNames = new Set();
    let ok = true;

    requiredFields.forEach((field) => {
      if (field.type === "radio") {
        if (checkedRadioNames.has(field.name)) {
          return;
        }

        checkedRadioNames.add(field.name);
        const anyChecked = form.querySelector(`input[type='radio'][name='${field.name}']:checked`);
        const hint = field.closest(".question")?.querySelector(".error") || field.parentElement.querySelector(".error");

        if (!anyChecked) {
          ok = false;
          if (hint) {
            hint.textContent = "請至少選擇一項";
          }
        } else if (hint) {
          hint.textContent = "";
        }
        return;
      }

      const hint = field.parentElement.querySelector(".error");
      if (!field.value.trim()) {
        ok = false;
        field.style.borderColor = "#dc2626";
        if (hint) {
          hint.textContent = "此欄位為必填";
        }
      } else {
        field.style.borderColor = "#d1d5db";
        if (hint) {
          hint.textContent = "";
        }
      }
    });

    if (!ok) {
      event.preventDefault();
      return;
    }

    event.preventDefault();
    const successBox = form.querySelector("[data-role='result']");
    if (successBox) {
      successBox.className = "alert alert-ok";
      successBox.textContent = "前端驗證通過，這是靜態 HTML 示範版（尚未送到後端）。";
    }
  });
}

function setupRegisterModeSwitch() {
  const existingMode = byId("club_mode_existing");
  const newMode = byId("club_mode_new");
  const existingWrap = byId("existingClubWrap");
  const newWrap = byId("newClubWrap");

  if (!existingMode || !newMode || !existingWrap || !newWrap) {
    return;
  }

  const apply = () => {
    if (newMode.checked) {
      existingWrap.style.display = "none";
      newWrap.style.display = "block";
    } else {
      existingWrap.style.display = "block";
      newWrap.style.display = "none";
    }
  };

  existingMode.addEventListener("change", apply);
  newMode.addEventListener("change", apply);
  apply();
}

renderFormCards();
setupSimpleValidation("loginForm");
setupSimpleValidation("registerForm");
setupSimpleValidation("submitForm");
setupRegisterModeSwitch();
