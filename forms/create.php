<?php
include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/header.php';

// 檢查權限
if (!in_array($current_user['role'], ['club_officer', 'admin'])) {
    redirect_to_login();
}

$form_id = $_GET['id'] ?? null;
$form = null;
$questions = [];

if ($form_id) {
    // 編輯模式
    try {
        $sql = "SELECT * FROM forms WHERE id = ? AND creator_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id, $current_user['id']]);
        $form = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$form) {
            redirect_to_login();
        }
        
        // 獲取題目
        $sql = "SELECT * FROM form_questions WHERE form_id = ? ORDER BY question_order";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$form_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        echo '錯誤：' . $e->getMessage();
        exit();
    }
}
?>

<div class="row">
    <div class="col-md-10">
        <h2 class="mb-4"><?php echo $form_id ? '編輯表單' : '建立新表單'; ?></h2>
        
        <div class="form-section">
            <form id="formBuilder" method="POST" action="/group_41/forms/save_form.php">
                <input type="hidden" name="form_id" value="<?php echo $form_id ?? ''; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                
                <div class="mb-3">
                    <label for="title" class="form-label">表單標題 <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required 
                           value="<?php echo $form ? escape($form['title']) : ''; ?>"
                           placeholder="例如：活動滿意度調查">
                </div>
                
                <div class="mb-3">
                    <label for="description" class="form-label">表單說明</label>
                    <textarea class="form-control" id="description" name="description" rows="3"
                              placeholder="詳細說明這份表單的目的"><?php echo $form ? escape($form['description']) : ''; ?></textarea>
                </div>
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="form_type" class="form-label">表單類型 <span class="text-danger">*</span></label>
                        <select class="form-select" id="form_type" name="form_type" required>
                            <option value="public" <?php echo ($form && $form['form_type'] == 'public') ? 'selected' : ''; ?>>公開</option>
                            <option value="club_only" <?php echo ($form && $form['form_type'] == 'club_only') ? 'selected' : ''; ?>>私人（僅限社團內成員）</option>
                        </select>
                    </div>
                    
                    <div class="col-md-6 mb-3">
                        <label class="form-label">可見範圍說明</label>
                        <div class="form-control bg-light" id="visibility_hint" style="height:auto; min-height:38px;">
                            公開：所有人可填寫
                        </div>
                        <small class="text-muted">私人表單會自動限制為你的社團：<?php echo escape($current_user['club_category']); ?></small>
                    </div>
                </div>
                
                <hr class="my-4">
                
                <h4 class="mb-3">題目設定</h4>
                
                <div id="questionsContainer">
                    <?php foreach ($questions as $index => $question): ?>
                        <div class="question-item card mb-3" data-question-id="<?php echo $question['id']; ?>">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label class="form-label">題目文字 <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control question-text" 
                                               value="<?php echo escape($question['question_text']); ?>" 
                                               placeholder="題目內容" required>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label class="form-label">題目類型 <span class="text-danger">*</span></label>
                                        <select class="form-select question-type" required>
                                            <option value="short_answer" <?php echo ($question['question_type'] == 'short_answer') ? 'selected' : ''; ?>>簡答題</option>
                                            <option value="long_answer" <?php echo ($question['question_type'] == 'long_answer') ? 'selected' : ''; ?>>詳答題</option>
                                            <option value="multiple_choice" <?php echo ($question['question_type'] == 'multiple_choice') ? 'selected' : ''; ?>>單選題</option>
                                            <option value="multi_choice" <?php echo ($question['question_type'] == 'multi_choice') ? 'selected' : ''; ?>>多選題</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input question-required" type="checkbox" 
                                               <?php echo $question['is_required'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label">為必填項目</label>
                                    </div>
                                </div>
                                
                                <div class="options-container" style="display: <?php echo in_array($question['question_type'], ['multiple_choice', 'multi_choice']) ? 'block' : 'none'; ?>;">
                                    <label class="form-label">選項列表</label>
                                    <?php
                                    $sql = "SELECT * FROM question_options WHERE question_id = ? ORDER BY option_order";
                                    $stmtOpts = $pdo->prepare($sql);
                                    $stmtOpts->execute([$question['id']]);
                                    $options = $stmtOpts->fetchAll(PDO::FETCH_ASSOC);
                                    ?>
                                    <div class="options-list">
                                        <?php foreach ($options as $opt_idx => $option): ?>
                                            <div class="option-item input-group mb-2">
                                                <input type="text" class="form-control option-text" 
                                                       value="<?php echo escape($option['option_text']); ?>" 
                                                       placeholder="選項 <?php echo $opt_idx + 1; ?>">
                                                <button type="button" class="btn btn-outline-danger remove-option">刪除</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-secondary add-option">+ 新增選項</button>
                                </div>
                                
                                <button type="button" class="btn btn-sm btn-outline-danger remove-question mt-3">🗑️ 刪除題目</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <button type="button" id="addQuestionBtn" class="btn btn-outline-primary mb-4">
                    + 新增題目
                </button>
                
                <hr class="my-4">
                
                <div class="mb-3">
                    <label for="status" class="form-label">表單狀態</label>
                    <select class="form-select" id="status" name="status">
                        <option value="draft" <?php echo (!$form || $form['status'] == 'draft') ? 'selected' : ''; ?>>草稿</option>
                        <option value="published" <?php echo ($form && $form['status'] == 'published') ? 'selected' : ''; ?>>已發布</option>
                    </select>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <?php echo $form_id ? '更新表單' : '建立表單'; ?>
                    </button>
                    <button type="button" id="goBackBtn" class="btn btn-outline-dark">返回上一層</button>
                    <a href="/group_41/forms/my_forms.php" class="btn btn-outline-secondary">返回</a>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.js"></script>
<script>
$(document).ready(function() {
    let questionCount = <?php echo count($questions); ?>;
    
    // 新增題目
    $('#addQuestionBtn').on('click', function() {
        questionCount++;
        const questionHTML = `
            <div class="question-item card mb-3" data-question-id="new_${questionCount}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label">題目文字 <span class="text-danger">*</span></label>
                            <input type="text" class="form-control question-text" placeholder="題目內容" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">題目類型 <span class="text-danger">*</span></label>
                            <select class="form-select question-type" required>
                                <option value="short_answer">簡答題</option>
                                <option value="long_answer">詳答題</option>
                                <option value="multiple_choice">單選題</option>
                                <option value="multi_choice">多選題</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-2">
                        <div class="form-check">
                            <input class="form-check-input question-required" type="checkbox" checked>
                            <label class="form-check-label">為必填項目</label>
                        </div>
                    </div>
                    
                    <div class="options-container" style="display: none;">
                        <label class="form-label">選項列表</label>
                        <div class="options-list"></div>
                        <button type="button" class="btn btn-sm btn-outline-secondary add-option">+ 新增選項</button>
                    </div>
                    
                    <button type="button" class="btn btn-sm btn-outline-danger remove-question mt-3">🗑️ 刪除題目</button>
                </div>
            </div>
        `;
        
        $('#questionsContainer').append(questionHTML);
    });
    
    // 題目類型改變
    $(document).on('change', '.question-type', function() {
        const optionsContainer = $(this).closest('.question-item').find('.options-container');
        if (['multiple_choice', 'multi_choice'].includes($(this).val())) {
            optionsContainer.slideDown();
            // 如果沒有選項，自動添加兩個
            if (optionsContainer.find('.option-item').length === 0) {
                optionsContainer.find('.options-list').html(`
                    <div class="option-item input-group mb-2">
                        <input type="text" class="form-control option-text" placeholder="選項 1">
                        <button type="button" class="btn btn-outline-danger remove-option">刪除</button>
                    </div>
                    <div class="option-item input-group mb-2">
                        <input type="text" class="form-control option-text" placeholder="選項 2">
                        <button type="button" class="btn btn-outline-danger remove-option">刪除</button>
                    </div>
                `);
            }
        } else {
            optionsContainer.slideUp();
        }
    });
    
    // 新增選項
    $(document).on('click', '.add-option', function() {
        const optionsList = $(this).siblings('.options-list');
        const optionIndex = optionsList.find('.option-item').length + 1;
        const newOption = `
            <div class="option-item input-group mb-2">
                <input type="text" class="form-control option-text" placeholder="選項 ${optionIndex}">
                <button type="button" class="btn btn-outline-danger remove-option">刪除</button>
            </div>
        `;
        optionsList.append(newOption);
    });
    
    // 刪除選項
    $(document).on('click', '.remove-option', function() {
        $(this).closest('.option-item').remove();
    });
    
    // 刪除題目
    $(document).on('click', '.remove-question', function() {
        if ($('.question-item').length === 1) {
            Swal.fire('無法刪除', '至少需要保留一個題目', 'warning');
            return;
        }
        $(this).closest('.question-item').fadeOut(300, function() {
            $(this).remove();
        });
    });
    
    // 表單提交
    $('#formBuilder').on('submit', function(e) {
        e.preventDefault();
        
        if ($('.question-item').length === 0) {
            Swal.fire('錯誤', '至少需要新增一個題目', 'error');
            return false;
        }
        
        // 構建題目數據
        const questions = [];
        $('.question-item').each(function(index) {
            const questionText = $(this).find('.question-text').val();
            const questionType = $(this).find('.question-type').val();
            const isRequired = $(this).find('.question-required').is(':checked');
            
            if (!questionText) {
                Swal.fire('錯誤', '所有題目文字都不能為空', 'error');
                throw new Error('Invalid question');
            }
            
            const options = [];
            if (['multiple_choice', 'multi_choice'].includes(questionType)) {
                $(this).find('.option-item').each(function() {
                    const optionText = $(this).find('.option-text').val();
                    if (optionText) {
                        options.push(optionText);
                    }
                });
                
                if (options.length < 2) {
                    Swal.fire('錯誤', '單選/多選題至少需要2個選項', 'error');
                    throw new Error('Invalid options');
                }
            }
            
            questions.push({
                id: $(this).data('question-id'),
                text: questionText,
                type: questionType,
                required: isRequired ? 1 : 0,
                options: options
            });
        });
        
        // 添加隱藏字段
        const data = new FormData(this);
        data.append('questions_json', JSON.stringify(questions));
        
        // 提交表單
        fetch('/group_41/forms/save_form.php', {
            method: 'POST',
            body: data
        })
        .then(async (response) => {
            const rawText = await response.text();
            let payload = null;

            try {
                payload = JSON.parse(rawText);
            } catch (parseErr) {
                const snippet = rawText ? rawText.substring(0, 500) : '（無回應內容）';
                throw new Error(`伺服器回應不是 JSON。\nHTTP ${response.status}\n${snippet}`);
            }

            if (!response.ok) {
                const msg = payload.message || `HTTP ${response.status}`;
                throw new Error(msg);
            }

            if (!payload.success) {
                throw new Error(payload.message || '提交失敗（未提供詳細訊息）');
            }

            return payload;
        })
        .then(payload => {
            if (payload.success) {
                Swal.fire('成功', payload.message, 'success').then(function() {
                    window.location.href = '/group_41/forms/my_forms.php';
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: '提交失敗',
                text: error.message || '發生未知錯誤',
                footer: '請將錯誤訊息提供給開發者，以便快速排查。'
            });
        });
        
        return false;
    });

    $('#form_type').on('change', function() {
        if ($(this).val() === 'club_only') {
            $('#visibility_hint').text('私人：僅限社團內成員（<?php echo escape($current_user['club_category']); ?>）可填寫');
        } else {
            $('#visibility_hint').text('公開：所有人可填寫');
        }
    }).trigger('change');

    $('#goBackBtn').on('click', function() {
        const fallback = '/group_41/forms/my_forms.php';
        const referrer = document.referrer || '';

        if (referrer && referrer.startsWith(window.location.origin)) {
            window.location.href = referrer;
            return;
        }

        window.location.href = fallback;
    });
});
</script>

<?php include_once $_SERVER['DOCUMENT_ROOT'] . '/group_41/includes/footer.php'; ?>

