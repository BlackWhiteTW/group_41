<div class="row">
    <div class="col-md-8">
        <div class="form-section">
            <?php if (!empty($access_error)): ?>
                <div class="alert alert-warning mt-2"><?php echo escape($access_error); ?></div>
            <?php else: ?>
                <h2><?php echo escape($form['title']); ?></h2>
                <p class="text-muted">
                    出題者: <strong><?php echo escape($form['username']); ?></strong> |
                    建立於: <?php echo date('Y-m-d H:i', strtotime($form['created_at'])); ?>
                </p>

                <?php if ($form['description']): ?>
                    <div class="alert alert-info">
                        <?php echo nl2br(escape($form['description'])); ?>
                    </div>
                <?php endif; ?>

                <form id="submitForm" method="POST" action="/group_41/forms/submit.php">
                    <input type="hidden" name="form_id" value="<?php echo (int)$form_id; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

                    <?php foreach ($questions as $question): ?>
                        <?php $questionId = (int)$question['id']; ?>
                        <div class="question-block card mb-4 p-3">
                            <h5>
                                <?php echo escape($question['question_text']); ?>
                                <?php if ($question['is_required']): ?>
                                    <span class="text-danger">*</span>
                                <?php endif; ?>
                            </h5>

                            <?php if ($question['question_type'] === 'short_answer'): ?>
                                <input type="text" class="form-control" name="answer_<?php echo $questionId; ?>"
                                    placeholder="請輸入您的答案"
                                    <?php echo $question['is_required'] ? 'required' : ''; ?>>

                            <?php elseif ($question['question_type'] === 'long_answer'): ?>
                                <textarea class="form-control" name="answer_<?php echo $questionId; ?>"
                                    rows="5" placeholder="請輸入您的詳細答案"
                                    <?php echo $question['is_required'] ? 'required' : ''; ?>></textarea>

                            <?php elseif ($question['question_type'] === 'multiple_choice'): ?>
                                <div class="mt-2">
                                    <?php foreach (($question_options[$questionId] ?? []) as $option): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio"
                                                name="answer_<?php echo $questionId; ?>"
                                                value="<?php echo (int)$option['id']; ?>"
                                                id="option_<?php echo (int)$option['id']; ?>"
                                                <?php echo $question['is_required'] ? 'required' : ''; ?>>
                                            <label class="form-check-label" for="option_<?php echo (int)$option['id']; ?>">
                                                <?php echo escape($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>

                            <?php elseif ($question['question_type'] === 'multi_choice'): ?>
                                <div class="mt-2">
                                    <?php foreach (($question_options[$questionId] ?? []) as $option): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox"
                                                name="answer_<?php echo $questionId; ?>[]"
                                                value="<?php echo (int)$option['id']; ?>"
                                                id="option_<?php echo $questionId; ?>_<?php echo (int)$option['id']; ?>">
                                            <label class="form-check-label" for="option_<?php echo $questionId; ?>_<?php echo (int)$option['id']; ?>">
                                                <?php echo escape($option['option_text']); ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        ✓ 提交表單
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">📊 表單資訊</h5>
                <hr>
                <p><strong>表單狀態：</strong>
                    <?php if ($form['status'] === 'draft'): ?>
                        <span class="badge bg-secondary">草稿</span>
                    <?php elseif ($form['status'] === 'published'): ?>
                        <span class="badge bg-success">已發布</span>
                    <?php else: ?>
                        <span class="badge bg-danger">已關閉</span>
                    <?php endif; ?>
                </p>
                <p><strong>表單類型：</strong> <?php echo ($form['form_type'] === 'public') ? '公開' : '私人（僅限社團內成員）'; ?></p>
                <p><strong>題目數量：</strong> <?php echo count($questions); ?></p>
                <p><strong>填寫人數：</strong> <?php echo (int)$submission_count; ?></p>
            </div>
        </div>
    </div>
</div>

<?php if (empty($access_error)): ?>
<script src="/group_41/js/jquery-3.7.1.min.js"></script>
<script src="/group_41/js/sweetalert2@11.js"></script>
<script>
$(document).ready(function() {
    $('#submitForm').on('submit', function(e) {
        let valid = true;

        $('input[required], textarea[required]').each(function() {
            if ($(this).attr('type') === 'radio') {
                const name = $(this).attr('name');
                if (!$(`input[name="${name}"]:checked`).length) {
                    valid = false;
                    $(this).addClass('is-invalid');
                }
            } else if (!$(this).val().trim()) {
                valid = false;
                $(this).addClass('is-invalid');
            }
        });

        $('.question-block').each(function() {
            const isRequired = $(this).find('.text-danger').length > 0;
            const checkboxes = $(this).find('input[type="checkbox"][name$="[]"]');
            if (isRequired && checkboxes.length > 0) {
                if (checkboxes.filter(':checked').length === 0) {
                    valid = false;
                    checkboxes.addClass('is-invalid');
                }
            }
        });

        if (!valid) {
            e.preventDefault();
            Swal.fire('欠缺資料', '請填寫所有必填項目', 'warning');
            return false;
        }
    });

    $('input, textarea').on('change input', function() {
        $(this).removeClass('is-invalid');
    });
});
</script>
<?php endif; ?>
