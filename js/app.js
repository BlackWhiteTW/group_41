// Shared frontend behavior
document.addEventListener('DOMContentLoaded', function(){
  var existingRadio = document.getElementById('club_mode_existing');
  var newRadio = document.getElementById('club_mode_new');
  var existingWrap = document.getElementById('existingClubWrap');
  var newWrap = document.getElementById('newClubWrap');
  if (existingRadio && newRadio && existingWrap && newWrap) {
    var syncClubMode = function(){
      var useExisting = existingRadio.checked;
      existingWrap.style.display = useExisting ? 'block' : 'none';
      newWrap.style.display = useExisting ? 'none' : 'block';
    };
    existingRadio.addEventListener('change', syncClubMode);
    newRadio.addEventListener('change', syncClubMode);
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

      var input = document.createElement('input');
      input.name = 'questions[' + questionId + '][options][]';
      input.placeholder = '選項內容';
      input.value = value || '';

      var removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-ghost btn-small';
      removeBtn.textContent = '刪除';
      removeBtn.setAttribute('data-action', 'remove-option');

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
        if (blocks.length <= 1) {
          clearQuestionBlock(questionBlock);
          return;
        }
        questionBlock.remove();
      }
    });
  }
});
