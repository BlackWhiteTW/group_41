// Shared frontend behavior
document.addEventListener('DOMContentLoaded', function(){
  var existingRadio = document.getElementById('club_mode_existing');
  var newRadio = document.getElementById('club_mode_new');
  var noneRadio = document.getElementById('club_mode_none');
  var existingWrap = document.getElementById('existingClubWrap');
  var newWrap = document.getElementById('newClubWrap');
  if (existingRadio && newRadio && existingWrap && newWrap) {
    var syncClubMode = function(){
      var useExisting = existingRadio.checked;
      var useNew = newRadio.checked;
      existingWrap.style.display = useExisting ? 'block' : 'none';
      newWrap.style.display = useNew ? 'block' : 'none';
    };
    existingRadio.addEventListener('change', syncClubMode);
    newRadio.addEventListener('change', syncClubMode);
    if (noneRadio) noneRadio.addEventListener('change', syncClubMode);
    syncClubMode();
  }

  var formType = document.getElementById('form_type');
  var targetWrap = document.getElementById('targetClubWrap');
  if (formType && targetWrap) {
    var syncTarget = function(){
      targetWrap.style.display = formType.value === 'club_only' ? 'block' : 'none';
    };
    formType.addEventListener('change', syncTarget);
    syncTarget();
  }

  var questionList = document.getElementById('questionList');
  var addQuestionBtn = document.getElementById('addQuestionBtn');
  var questionTemplate = document.getElementById('questionTemplate');
  if (questionList && addQuestionBtn && questionTemplate) {
    var nextIndex = parseInt(questionList.getAttribute('data-next-index'), 10);
    if (isNaN(nextIndex)) {
      nextIndex = questionList.children.length;
    }

    var isChoiceType = function(value) {
      return value === 'multiple_choice' || value === 'multi_choice';
    };

    var createOptionRow = function(questionId, value) {
      var row = document.createElement('div');
      row.className = 'option-row';

      var hiddenId = document.createElement('input');
      hiddenId.type = 'hidden';
      hiddenId.name = 'questions[' + questionId + '][opt_ids][]';
      hiddenId.value = '0';

      var input = document.createElement('input');
      input.name = 'questions[' + questionId + '][options][]';
      input.placeholder = '選項內容';
      input.value = value || '';

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-ghost btn-small';
      removeBtn.textContent = '刪除';
      removeBtn.setAttribute('data-action', 'remove-option');

      row.appendChild(hiddenId);
      row.appendChild(input);
      row.appendChild(removeBtn);
      return row;
    };

    var syncQuestionOptions = function(block) {
      if (!block) {
        return;
      }
      var typeSelect = block.querySelector('[data-role="question-type"]');
      var optionGroup = block.querySelector('[data-role="option-group"]');
      if (!typeSelect || !optionGroup) {
        return;
      }
      var isChoice = isChoiceType(typeSelect.value);
      optionGroup.style.display = isChoice ? 'block' : 'none';
      if (!isChoice) {
        return;
      }
      var questionId = block.getAttribute('data-question-block');
      var options = block.querySelector('.options');
      if (!options || !questionId) {
        return;
      }
      var rows = options.querySelectorAll('.option-row');
      if (rows.length === 0) {
        options.appendChild(createOptionRow(questionId, ''));
        options.appendChild(createOptionRow(questionId, ''));
      }
    };

    var clearQuestionBlock = function(block) {
      if (!block) {
        return;
      }
      var questionId = block.getAttribute('data-question-block');
      var textInput = block.querySelector('input[name="questions[' + questionId + '][text]"]') || block.querySelector('input[name*="[text]"]');
      if (textInput) {
        textInput.value = '';
      }
      var typeSelect = block.querySelector('[data-role="question-type"]');
      if (typeSelect) {
        typeSelect.value = 'short_answer';
      }
      var requiredInput = block.querySelector('input[type="checkbox"][name*="[required]"]');
      if (requiredInput) {
        requiredInput.checked = true;
      }
      var options = block.querySelector('.options');
      if (options && questionId) {
        options.innerHTML = '';
        options.appendChild(createOptionRow(questionId, ''));
        options.appendChild(createOptionRow(questionId, ''));
      }
      syncQuestionOptions(block);
    };

    var initQuestionBlock = function(block) {
      syncQuestionOptions(block);
    };

    Array.prototype.forEach.call(
      questionList.querySelectorAll('[data-question-block]'),
      initQuestionBlock
    );

    addQuestionBtn.addEventListener('click', function(){
      var html = questionTemplate.innerHTML.replace(/__INDEX__/g, String(nextIndex));
      var wrapper = document.createElement('div');
      wrapper.innerHTML = html.trim();
      if (wrapper.firstElementChild) {
        questionList.appendChild(wrapper.firstElementChild);
        initQuestionBlock(wrapper.firstElementChild);
        nextIndex += 1;
        questionList.setAttribute('data-next-index', String(nextIndex));
      }
    });

    questionList.addEventListener('change', function(event){
      var target = event.target;
      if (!target || target.getAttribute('data-role') !== 'question-type') {
        return;
      }
      var block = target.closest('[data-question-block]');
      syncQuestionOptions(block);
    });

    questionList.addEventListener('click', function(event){
      var target = event.target;
      if (!target) {
        return;
      }

      var action = target.getAttribute('data-action');
      if (action === 'add-option') {
        var block = target.closest('[data-question-block]');
        if (!block) {
          return;
        }
        var questionId = target.getAttribute('data-question') || block.getAttribute('data-question-block');
        if (!questionId) {
          return;
        }
        var options = block.querySelector('.options');
        if (!options) {
          return;
        }
        options.appendChild(createOptionRow(questionId, ''));
        return;
      }

      if (action === 'remove-option') {
        var row = target.closest('.option-row');
        if (!row) {
          return;
        }
        var optionList = row.parentElement;
        if (!optionList) {
          return;
        }
        var rows = optionList.querySelectorAll('.option-row');
        if (rows.length <= 1) {
          var input = row.querySelector('input');
          if (input) {
            input.value = '';
          }
          return;
        }
        optionList.removeChild(row);
        return;
      }

      if (action === 'remove-question') {
        var questionBlock = target.closest('[data-question-block]');
        if (!questionBlock) {
          return;
        }
        var blocks = questionList.querySelectorAll('[data-question-block]');
        var doRemove = function(){
          if (blocks.length <= 1) {
            clearQuestionBlock(questionBlock);
            return;
          }
          questionBlock.remove();
        };
        if (typeof Swal === 'undefined') {
          if (confirm('確定要刪除此題目嗎？')) doRemove();
          return;
        }
        Swal.fire({
          title: '刪除題目',
          text: '確定要刪除此題目嗎？',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonText: '確定',
          cancelButtonText: '取消',
          confirmButtonColor: '#dc3545'
        }).then(function(result){
          if (result.isConfirmed) doRemove();
        });
        return;
      }

      if (action === 'move-up') {
        var block = target.closest('[data-question-block]');
        if (!block) return;
        var prev = block.previousElementSibling;
        if (prev && prev.hasAttribute('data-question-block')) {
          questionList.insertBefore(block, prev);
        }
        return;
      }

      if (action === 'move-down') {
        var block = target.closest('[data-question-block]');
        if (!block) return;
        var next = block.nextElementSibling;
        if (next && next.hasAttribute('data-question-block')) {
          questionList.insertBefore(next, block);
        }
        return;
      }
    });
  }

  var usernameInput = document.getElementById('reg_username');
  var usernameStatus = document.getElementById('username-status');
  if (usernameInput && usernameStatus) {
    var debounceTimer = null;
    usernameInput.addEventListener('input', function(){
      clearTimeout(debounceTimer);
      var val = this.value.trim();
      if (val.length < 3) {
        usernameStatus.style.display = 'none';
        return;
      }
      debounceTimer = setTimeout(function(){
        fetch('api/check_username.php?q=' + encodeURIComponent(val))
          .then(function(r){ return r.json(); })
          .then(function(data){
            usernameStatus.style.display = 'block';
            if (data.available) {
              usernameStatus.style.color = '#28a745';
              usernameStatus.textContent = '✓ ' + data.message;
            } else {
              usernameStatus.style.color = '#dc3545';
              usernameStatus.textContent = '✗ ' + data.message;
            }
          })
          .catch(function(){
            usernameStatus.style.display = 'none';
          });
      }, 400);
    });
  }

  var forms = document.querySelectorAll('form[method="post"]');
  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function(e) {
      if (this.id === 'registerForm' || this.id === 'submitForm') return;
      var btn = this.querySelector('button[type="submit"], input[type="submit"]');
      if (!btn) return;
      if (btn.dataset.originalText === undefined) {
        btn.dataset.originalText = btn.tagName === 'INPUT' ? (btn.value || '送出') : (btn.textContent || '送出');
      }
      btn.disabled = true;
      if (btn.tagName === 'INPUT') {
        btn.value = '送出中...';
      } else {
        btn.textContent = '送出中...';
      }
    });
  }

  var submitForm = document.getElementById('submitForm');
  if (submitForm) {
    submitForm.addEventListener('submit', function(e) {
      var fields = this.querySelectorAll('[data-required="1"]');
      var firstMissing = null;
      var hasError = false;
      for (var i = 0; i < fields.length; i++) {
        var field = fields[i];
        var errorEl = field.querySelector('.field-error');
        var filled = false;

        if (field.querySelector('input[type="file"]')) {
          filled = !!field.querySelector('input[type="file"]').value;
        } else if (field.querySelector('input[type="radio"]')) {
          filled = !!field.querySelector('input[type="radio"]:checked');
        } else if (field.querySelector('input[type="checkbox"]')) {
          filled = !!field.querySelector('input[type="checkbox"]:checked');
        } else if (field.querySelector('textarea')) {
          filled = field.querySelector('textarea').value.trim() !== '';
        } else {
          var input = field.querySelector('input:not([type="hidden"])');
          filled = input && input.value.trim() !== '';
        }

        if (!filled) {
          hasError = true;
          if (errorEl) errorEl.style.display = 'block';
          if (!firstMissing) firstMissing = field;
        } else {
          if (errorEl) errorEl.style.display = 'none';
        }
      }

      if (hasError) {
        e.preventDefault();
        if (firstMissing) firstMissing.scrollIntoView({ behavior: 'smooth', block: 'center' });
        var btn = this.querySelector('button[type="submit"]');
        if (btn) {
          btn.disabled = false;
          btn.textContent = btn.dataset.originalText || '送出';
        }
        return;
      }
    });
  }

  var registerForm = document.getElementById('registerForm');
  if (registerForm) {
    registerForm.addEventListener('submit', function(e) {
      e.preventDefault();
      var btn = this.querySelector('button[type="submit"]');
      var username = (document.getElementById('reg_username') || {}).value || '';
      var email = (document.getElementById('reg_email') || {}).value || '';
      var password = (document.getElementById('reg_password') || {}).value || '';
      var passwordConfirm = (document.getElementById('reg_password_confirm') || {}).value || '';
      var errors = [];

      if (username.trim().length < 3) {
        errors.push('帳號至少需要 3 個字元');
      }

      var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
      if (!emailRegex.test(email.trim())) {
        errors.push('請輸入有效的電子郵件');
      }

      if (password.length < 6) {
        errors.push('密碼至少需要 6 個字元');
      }

      if (password !== passwordConfirm) {
        errors.push('密碼與確認密碼不相符');
      }

      var clubMode = document.querySelector('input[name="club_mode"]:checked');
      if (clubMode && clubMode.value === 'existing') {
        var existingClub = (document.getElementById('existing_club') || {}).value || '';
        if (!existingClub) {
          errors.push('請選擇一個社團');
        }
      } else if (clubMode && clubMode.value === 'new') {
        var newClubName = (document.getElementById('new_club_name') || {}).value || '';
        if (newClubName.trim() === '') {
          errors.push('請輸入新社團名稱');
        }
      }

      if (errors.length > 0) {
        if (typeof Swal !== 'undefined') {
          Swal.fire({
            title: '驗證失敗',
            html: errors.map(function(m){ return '&bull; ' + m; }).join('<br>'),
            icon: 'error',
            confirmButtonText: '確定'
          });
        } else {
          alert(errors.join('\n'));
        }
        if (btn) {
          btn.disabled = false;
          btn.textContent = btn.dataset.originalText || '註冊';
        }
        return;
      }
      if (btn) {
        btn.disabled = true;
        if (btn.dataset.originalText === undefined) {
          btn.dataset.originalText = btn.textContent || '註冊';
        }
        btn.textContent = '註冊中...';
      }
      this.submit();
    });
  }
});

document.addEventListener('click', function(e){
	var el = e.target.closest('[data-confirm]');
	if (!el) return;
	e.preventDefault();
	var msg = el.getAttribute('data-confirm') || '確定執行此操作？';
	if (typeof Swal === 'undefined') {
		if (confirm(msg)) {
			if (el.tagName === 'A') { window.location.href = el.href; }
			else if (el.tagName === 'FORM') { el.submit(); }
			else { el.closest('form') ? el.closest('form').submit() : null; }
		}
		return;
	}
	Swal.fire({
		title: '確認操作',
		text: msg,
		icon: 'warning',
		showCancelButton: true,
		confirmButtonText: '確定',
		cancelButtonText: '取消',
		confirmButtonColor: '#dc3545'
	}).then(function(result){
		if (!result.isConfirmed) return;
		if (el.tagName === 'A') { window.location.href = el.href; }
		else if (el.tagName === 'FORM') { el.submit(); }
		else { var f = el.closest('form'); if (f) f.submit(); }
	});
});
