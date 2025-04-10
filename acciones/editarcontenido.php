<?php
// Pantalla para editar SOLO el contenido en Markdown (split screen)
        $id = $_GET['id'] ?? '';
        $stmt = $db->prepare("SELECT * FROM publications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $pub = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pub) {
            echo "<p>No se encontró la publicación con ID = $id</p>";
        } else {
            ?>
            <div class="publications-container">
               <h2>Editar Contenido: <?php echo htmlspecialchars($pub['title']); ?></h2>

                
                <!-- Where we'll show success if we do AJAX save -->
                <p class="update-message" id="ajaxSuccessMessage" style="display:none;"></p>

                <form method="post" class="edit-form-markdown" id="editContentForm">
                    <input type="hidden" name="id" value="<?php echo $pub['id']; ?>">

                    <!-- Zona de split screen -->
                    <div class="markdown-container">
                        <!-- Columna izquierda: textarea con Markdown -->
                        <div class="markdown-editor" id="markdownEditorWrapper">
                            <label for="content">Contenido (Markdown)</label>
                            <textarea name="content" id="content" rows="20"><?php echo htmlspecialchars($pub['content']); ?></textarea>
                        </div>
                        <!-- Columna derecha: Vista previa -->
                        <div class="markdown-preview" id="markdownPreview"></div>
                    </div>

                    <!-- Botón que hace un POST normal (reload) -->
                    <button type="submit" name="update_content_back">
                        Guardar y volver al Dashboard
                    </button>
                    
                    <!-- Botón que hará AJAX (sin recargar la página) -->
                    <button type="button" id="ajaxStayBtn">
                        Guardar y permanecer (AJAX)
                    </button>
                    
                    <!-- Botón para renderizar a PDF (abre en una nueva pestaña) -->
                    <button type="button" onclick="window.open('pdf.php?id=<?php echo $pub['id']; ?>', '_blank')">
                        Obtener PDF
                    </button>
                    
                    <button type="button" onclick="window.open('markdown.php?id=<?php echo $pub['id']; ?>', '_blank')">
								 Obtener Markdown
							</button>
                </form>
            </div>

            <script>
            (function(){
                // -------------------------------------------------------------
                // SCROLL SYNC AND LIVE PREVIEW
                // -------------------------------------------------------------
                const textarea  = document.getElementById('content');
                const preview   = document.getElementById('markdownPreview');
                const editorDiv = document.getElementById('markdownEditorWrapper');
                
                let typingTimer;
                const doneTypingInterval = 400;

                // Render markdown by calling your existing parser via AJAX
                function updatePreview() {
                    const markdown = textarea.value;
                    fetch('inc/jocarsa | navy.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ ajax_markdown: markdown })
                    })
                    .then(response => response.text())
                    .then(html => {
                        preview.innerHTML = html;
                        addNumberingAndTOC(); // from your existing code snippet
                    })
                    .catch(err => console.error('Error fetching markdown preview:', err));
                }

                function doneTyping() {
                    clearTimeout(typingTimer);
                    typingTimer = setTimeout(updatePreview, doneTypingInterval);
                }

                // If you have a function addNumberingAndTOC() from your snippet, you can place it here:
                function addNumberingAndTOC() {
                    // Example from your snippet:
                    const headers = preview.querySelectorAll('h1, h2, h3, h4, h5, h6');
                    const numbering = [0,0,0,0,0,0];
                    let tocHTML = '<h2>Índice de contenido</h2><ul>';

                    headers.forEach(header => {
                        const level = parseInt(header.tagName.substring(1));
                        numbering[level - 1]++;
                        for (let i = level; i < numbering.length; i++) {
                            numbering[i] = 0;
                        }
                        const numStr = numbering.slice(0, level).join('.') + '. ';
                        header.textContent = numStr + header.textContent;

                        if (!header.id) {
                            header.id = 'heading-' + Math.random().toString(36).substr(2,9);
                        }
                        tocHTML += `<li style="margin-left: ${(level - 1) * 20}px;">
                                      <a href="#${header.id}">${header.textContent}</a>
                                    </li>`;
                    });

                    tocHTML += '</ul>';
                    const tocContainer = document.createElement('div');
                    tocContainer.classList.add('table-of-contents');
                    tocContainer.innerHTML = tocHTML;
                    preview.insertBefore(tocContainer, preview.firstChild);
                }

                // Listen for input on the textarea
                textarea.addEventListener('input', doneTyping);
                // Sync scroll ratio
                editorDiv.addEventListener('scroll', () => {
                    const ratio = editorDiv.scrollTop / 
                                  (editorDiv.scrollHeight - editorDiv.clientHeight);
                    preview.scrollTop = ratio * (preview.scrollHeight - preview.clientHeight);
                });

                // Initialize once
                updatePreview();

                // -------------------------------------------------------------
                // AJAX SAVE (Guardar y permanecer)
                // -------------------------------------------------------------
                const stayBtn       = document.getElementById('ajaxStayBtn');
                const successMsgDiv = document.getElementById('ajaxSuccessMessage');
                const form          = document.getElementById('editContentForm');

                stayBtn.addEventListener('click', function(e) {
                    e.preventDefault(); // do not do a normal submit
                    // Prepare data
                    const formData = new FormData(form);
                    // Send AJAX
                    fetch('index.php?action=edit_content&ajax=1&id=<?php echo $pub["id"]; ?>', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(json => {
                        if (json.success) {
                            successMsgDiv.textContent = json.message;
                            successMsgDiv.style.display = 'block';
                        } else {
                            successMsgDiv.textContent = 'Error al actualizar.';
                            successMsgDiv.style.display = 'block';
                        }
                    })
                    .catch(err => {
                        console.error(err);
                        successMsgDiv.textContent = 'Error de red o servidor.';
                        successMsgDiv.style.display = 'block';
                    });
                });

                // -------------------------------------------------------------
                // (OPTIONAL) REMEMBER SCROLL & CURSOR IF PAGE RELOADS
                // -------------------------------------------------------------
                // If you want to remember the exact position after a normal POST:
                form.addEventListener('submit', () => {
                    sessionStorage.setItem('editorScroll', editorDiv.scrollTop);
                    sessionStorage.setItem('editorCursor', textarea.selectionStart);
                });

                // On load, restore
                window.addEventListener('DOMContentLoaded', () => {
                    const storedScroll = sessionStorage.getItem('editorScroll');
                    const storedCursor = sessionStorage.getItem('editorCursor');
                    if (storedScroll !== null) {
                        editorDiv.scrollTop = parseInt(storedScroll, 10);
                    }
                    if (storedCursor !== null) {
                        textarea.selectionStart = parseInt(storedCursor, 10);
                        textarea.selectionEnd   = parseInt(storedCursor, 10);
                    }
                });
            })();
            </script>
            <?php
        }
?>
