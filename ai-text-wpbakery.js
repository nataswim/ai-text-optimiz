(function() {
    'use strict';
    
    console.log(' AI Text TinyMCE - Version 2.1 - Démarrage...');
    
    // Variables globales
    let waitAttempts = 0;
    const maxWaitAttempts = 20;
    let editorsProcessed = new Set();
    let buttonsInitialized = false;
    let debugMode = true;
    
    // Fonction de debug
    function debugLog(message, data = null) {
        if (debugMode) {
            console.log('🤖 AI Text:', message, data || '');
        }
    }
    
    // Vérifier les prérequis
    function checkPrerequisites() {
        debugLog('🔍 Vérification des prérequis...');
        
        if (typeof aiTextVars === 'undefined') {
            console.error('❌ aiTextVars non défini');
            return false;
        }
        
        debugLog(' Variables WordPress chargées:', aiTextVars);
        
        if (!aiTextVars.isWooCommerce || !aiTextVars.isProduct) {
            debugLog('ℹ️ Pas une page produit WooCommerce');
            return false;
        }
        
        debugLog(' Page produit WooCommerce détectée');
        return true;
    }
    
    // Fonction pour créer et afficher le menu IA
    function showAIMenu(editor) {
        if (!editor) {
            showNotification('Éditeur non trouvé', 'error');
            return;
        }
        
        const selectedContent = editor.selection.getContent();
        if (!selectedContent || selectedContent.trim() === '') {
            showNotification('Sélectionnez le texte à optimiser.', 'warning');
            return;
        }
        
        debugLog('Affichage du menu IA pour:', editor.id);
        
        // Supprimer menu existant
        const existingMenu = document.getElementById('ai-text-menu');
        if (existingMenu) {
            existingMenu.remove();
        }
        
        // Créer le menu
        const menu = document.createElement('div');
        menu.id = 'ai-text-menu';
        menu.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border: 2px solid #0073aa;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            z-index: 999999;
            min-width: 320px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            font-size: 14px;
        `;
        
        menu.innerHTML = `
            <div style="padding: 15px; border-bottom: 1px solid #eee; font-weight: bold; background: #0073aa; color: white; border-radius: 6px 6px 0 0;">
                 Optimiser avec l'IA
            </div>
            <div class="ai-menu-item" data-action="optimize" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;">
                 Optimiser (SEO + Style)
            </div>
            <div class="ai-menu-item" data-action="correct" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;">
                 Corriger les fautes
            </div>
            <div class="ai-menu-item" data-action="enrich" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;">
                 Enrichir le contenu
            </div>
            <div class="ai-menu-item" data-action="create_content" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s;">
                 Créer du contenu web
            </div>
            <div class="ai-menu-item" data-action="demo" style="padding: 12px 15px; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s; color: #666; font-style: italic;">
                 Mode Démo (Test sans API)
            </div>
            <div style="padding: 10px; text-align: center;">
                <button id="ai-close-menu" style="padding: 6px 12px; background: #666; color: white; border: none; border-radius: 4px; cursor: pointer;">Annuler</button>
            </div>
        `;
        
        document.body.appendChild(menu);
        
        // Gérer les clics sur les options
        const menuItems = menu.querySelectorAll('.ai-menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                menu.remove();
                processAIText(action, editor, selectedContent);
            });
            
            // Effets hover
            item.addEventListener('mouseenter', function() {
                this.style.background = '#f0f7ff';
            });
            item.addEventListener('mouseleave', function() {
                this.style.background = 'white';
            });
        });
        
        // Bouton fermer
        const closeBtn = menu.querySelector('#ai-close-menu');
        closeBtn.addEventListener('click', function() {
            menu.remove();
        });
        
        // Fermer en cliquant à côté
        setTimeout(() => {
            document.addEventListener('click', function closeMenu(e) {
                if (!menu.contains(e.target)) {
                    menu.remove();
                    document.removeEventListener('click', closeMenu);
                }
            });
        }, 100);
    }
    
    // Traitement IA avec appel AJAX réel
    function processAIText(actionType, editor, selectedContent) {
        debugLog('Traitement IA:', actionType);
        
        if (!editor) {
            showNotification('Erreur : Éditeur non trouvé', 'error');
            return;
        }
        
        // Afficher le statut de chargement
        if (editor.setProgressState) {
            editor.setProgressState(true);
        }
        showNotification('Traitement en cours...', 'info');
        
        // Préparer les données
        const title = document.getElementById('title')?.value || '';
        const formData = new FormData();
        
        // Mode démo ou mode normal
        if (actionType === 'demo') {
            formData.append('action', 'ai_text_demo');
            formData.append('action_type', 'optimize');
        } else {
            formData.append('action', 'ai_text_process');
            formData.append('action_type', actionType);
        }
        
        formData.append('nonce', aiTextVars.nonce);
        formData.append('title', title);
        formData.append('content', selectedContent);
        
        // Appel AJAX avec fetch
        fetch(aiTextVars.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            if (editor.setProgressState) {
                editor.setProgressState(false);
            }
            
            if (data.success && data.data && data.data.trim() !== '') {
                if (actionType === 'create_content' || actionType === 'demo') {
                    showContentPreview(editor, data.data);
                } else {
                    editor.selection.setContent(data.data);
                    showNotification('Contenu optimisé avec succès !', 'success');
                }
            } else {
                showNotification(data.data || 'Aucune réponse de l\'IA.', 'error');
            }
        })
        .catch(error => {
            if (editor.setProgressState) {
                editor.setProgressState(false);
            }
            console.error('Erreur AJAX:', error);
            showNotification('Erreur de connexion.', 'error');
        });
    }
    
    // Prévisualisation du contenu créé
    function showContentPreview(editor, content) {
        const modal = document.createElement('div');
        modal.id = 'ai-text-preview-modal';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999999;
            display: flex;
            align-items: center;
            justify-content: center;
        `;
        
        modal.innerHTML = `
            <div style="
                background: white;
                border-radius: 8px;
                padding: 20px;
                max-width: 80%;
                max-height: 80%;
                overflow-y: auto;
                box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            ">
                <h3 style="margin-top: 0;">Prévisualisation du contenu généré</h3>
                <div style="
                    border: 1px solid #ddd;
                    padding: 15px;
                    margin: 15px 0;
                    max-height: 400px;
                    overflow-y: auto;
                    background: #f9f9f9;
                ">${content}</div>
                <div style="text-align: right; margin-top: 15px;">
                    <button id="ai-text-cancel" style="
                        padding: 8px 16px;
                        margin-right: 10px;
                        background: #666;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Annuler</button>
                    <button id="ai-text-insert" style="
                        padding: 8px 16px;
                        background: #0073aa;
                        color: white;
                        border: none;
                        border-radius: 4px;
                        cursor: pointer;
                    ">Insérer le contenu</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        
        modal.querySelector('#ai-text-insert').addEventListener('click', function() {
            editor.selection.setContent(content);
            modal.remove();
            showNotification('Contenu inséré avec succès !', 'success');
        });
        
        modal.querySelector('#ai-text-cancel').addEventListener('click', function() {
            modal.remove();
        });
        
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.remove();
            }
        });
    }
    
    // Ajouter le bouton IA à un éditeur
    function addAIButtonToEditor(editor) {
        if (!editor || editorsProcessed.has(editor.id)) {
            return;
        }
        
        debugLog('Tentative d\'ajout du bouton IA à:', editor.id);
        
        const editorContainer = editor.getContainer();
        if (!editorContainer) {
            debugLog('Container non trouvé pour:', editor.id);
            return;
        }
        
        // Méthode 1 : Dans la toolbar TinyMCE
        const toolbars = [
            editorContainer.querySelector('.mce-toolbar-grp'),
            editorContainer.querySelector('.mce-toolbar'),
            editorContainer.querySelector('.mce-top-part'),
            editorContainer.querySelector('[role="toolbar"]')
        ].filter(Boolean);
        
        if (toolbars.length > 0) {
            const toolbar = toolbars[0];
            
            // Vérifier si le bouton existe déjà
            if (!toolbar.querySelector('.ai-text-btn')) {
                const aiButton = document.createElement('button');
                aiButton.className = 'ai-text-btn mce-btn';
                aiButton.innerHTML = '🤖 IA';
                aiButton.title = 'Optimiser avec l\'IA';
                aiButton.style.cssText = `
                    background: #0073aa;
                    color: white;
                    border: none;
                    padding: 6px 12px;
                    margin: 2px;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 12px;
                    font-weight: bold;
                    height: 30px;
                `;
                
                aiButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    showAIMenu(editor);
                });
                
                toolbar.appendChild(aiButton);
                debugLog(' Bouton IA ajouté dans la toolbar de:', editor.id);
                editorsProcessed.add(editor.id);
                return;
            }
        }
        
        // Méthode 2 : Position absolue sur l'éditeur
        if (!editorContainer.querySelector('.ai-text-btn')) {
            const buttonContainer = document.createElement('div');
            buttonContainer.style.cssText = `
                position: absolute;
                top: 5px;
                right: 5px;
                z-index: 1000;
            `;
            
            const aiButton = document.createElement('button');
            aiButton.className = 'ai-text-btn';
            aiButton.innerHTML = '🤖 IA';
            aiButton.title = 'Optimiser avec l\'IA';
            aiButton.style.cssText = `
                background: #0073aa;
                color: white;
                border: none;
                padding: 8px 12px;
                border-radius: 4px;
                cursor: pointer;
                font-size: 12px;
                font-weight: bold;
                box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            `;
            
            aiButton.addEventListener('click', function(e) {
                e.preventDefault();
                showAIMenu(editor);
            });
            
            buttonContainer.appendChild(aiButton);
            editorContainer.style.position = 'relative';
            editorContainer.appendChild(buttonContainer);
            
            debugLog(' Bouton IA ajouté en position absolue à:', editor.id);
            editorsProcessed.add(editor.id);
        }
    }
    
    // Fonction d'attente pour TinyMCE
    function waitForTinyMCE() {
        if (waitAttempts >= maxWaitAttempts) {
            debugLog('Timeout: TinyMCE non détecté');
            return;
        }
        
        if (typeof tinymce === 'undefined' || !tinymce.editors) {
            waitAttempts++;
            setTimeout(waitForTinyMCE, 500);
            return;
        }
        
        debugLog('TinyMCE détecté, ajout des boutons IA...');
        
        // Ajouter le bouton à tous les éditeurs existants
        tinymce.editors.forEach(function(editor) {
            if (editor && editor.initialized) {
                addAIButtonToEditor(editor);
            }
        });
        
        // Écouter les nouveaux éditeurs
        if (tinymce.on && typeof tinymce.on === 'function') {
            tinymce.on('AddEditor', function(e) {
                debugLog('Nouvel éditeur détecté:', e.editor.id);
                setTimeout(() => addAIButtonToEditor(e.editor), 500);
            });
        }
        
        // Support spécifique pour WPBakery
        document.addEventListener('vc_wpb_tinymce_init', function(event) {
            if (event.detail && event.detail.editor) {
                debugLog('Éditeur WPBakery détecté:', event.detail.editor.id);
                setTimeout(() => addAIButtonToEditor(event.detail.editor), 100);
            }
        });
        
        // Vérification périodique pour les nouveaux éditeurs
        setInterval(function() {
            if (typeof tinymce !== 'undefined' && tinymce.editors) {
                tinymce.editors.forEach(function(editor) {
                    if (editor && editor.initialized && !editorsProcessed.has(editor.id)) {
                        addAIButtonToEditor(editor);
                    }
                });
            }
        }, 2000);
    }
    
    // Système de notifications amélioré
    function showNotification(message, type) {
        type = type || 'info';
        
        // Supprimer les notifications existantes
        const existing = document.querySelectorAll('.ai-text-notification');
        existing.forEach(el => el.remove());
        
        const notification = document.createElement('div');
        notification.className = `ai-text-notification ai-text-${type}`;
        notification.innerHTML = `
            <div style="display: flex; align-items: center; gap: 8px;">
                <div style="flex-shrink: 0;">
                    ${type === 'success' ? '' : type === 'error' ? '❌' : type === 'warning' ? '⚠️' : 'ℹ️'}
                </div>
                <div style="flex: 1;">${message}</div>
            </div>
        `;
        
        notification.style.cssText = `
            position: fixed;
            top: 32px;
            right: 20px;
            padding: 12px 16px;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 999999;
            font-size: 14px;
            font-weight: 500;
            min-width: 280px;
            max-width: 400px;
            word-wrap: break-word;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            transition: all 0.3s ease;
        `;
        
        // Styles selon le type
        const styles = {
            success: { background: '#00a32a', color: 'white' },
            error: { background: '#d63638', color: 'white' },
            warning: { background: '#f56e28', color: 'white' },
            info: { background: '#0073aa', color: 'white' }
        };
        
        const style = styles[type] || styles.info;
        notification.style.background = style.background;
        notification.style.color = style.color;
        
        document.body.appendChild(notification);
        
        // Auto-suppression
        const duration = type === 'error' ? 8000 : 5000;
        setTimeout(function() {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        }, duration);
        
        // Clic pour fermer
        notification.style.cursor = 'pointer';
        notification.addEventListener('click', function() {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 300);
        });
    }
    
    // FONCTIONS WOOCOMMERCE - VERSION FORCÉE
    function addWooCommerceButtons() {
        debugLog('🛒 Démarrage addWooCommerceButtons...');
        
        if (buttonsInitialized) {
            debugLog(' Boutons déjà initialisés');
            return true;
        }
        
        if (!checkPrerequisites()) {
            debugLog('❌ Prérequis non remplis');
            return false;
        }
        
        debugLog('🔧 Ajout forcé des boutons WooCommerce...');
        
        let titleSuccess = false;
        let slugSuccess = false;
        
        // AJOUTER LE BOUTON TITRE - VERSION FORCÉE
        try {
            const titleField = document.getElementById('title');
            if (titleField && !document.querySelector('.ai-title-button')) {
                debugLog('📝 Création du bouton titre...');
                
                const aiButton = document.createElement('button');
                aiButton.type = 'button';
                aiButton.className = 'ai-woo-button ai-title-button';
                aiButton.innerHTML = '🤖 Optimiser le titre';
                aiButton.title = 'Optimiser le titre avec l\'IA';
                aiButton.style.cssText = `
                    background: #0073aa !important;
                    color: white !important;
                    border: none !important;
                    padding: 8px 12px !important;
                    margin: 8px 0 0 0 !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    font-size: 12px !important;
                    font-weight: 500 !important;
                    display: inline-block !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
                    transition: all 0.2s ease !important;
                `;
                
                aiButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    optimizeTitle();
                });
                
                // Insérer le bouton après le champ titre
                const container = document.createElement('div');
                container.className = 'ai-title-container';
                container.style.cssText = 'margin-top: 8px !important; display: block !important;';
                container.appendChild(aiButton);
                
                const titleWrap = titleField.closest('#titlewrap') || titleField.closest('#titlediv') || titleField.parentNode;
                titleWrap.insertAdjacentElement('afterend', container);
                
                titleSuccess = true;
                debugLog(' Bouton titre créé avec succès');
            } else {
                debugLog('ℹ️ Bouton titre déjà présent ou champ non trouvé');
                titleSuccess = !!document.querySelector('.ai-title-button');
            }
        } catch (error) {
            debugLog('❌ Erreur création bouton titre:', error);
        }
        
        // AJOUTER LE BOUTON SLUG - VERSION FORCÉE
        try {
            const slugField = document.getElementById('post_name') || 
                             document.querySelector('input[name="post_name"]');
            
            if (slugField && !document.querySelector('.ai-slug-button')) {
                debugLog('🔗 Création du bouton slug...');
                
                const aiButton = document.createElement('button');
                aiButton.type = 'button';
                aiButton.className = 'ai-woo-button ai-slug-button';
                aiButton.innerHTML = '🔗 Optimiser le slug';
                aiButton.title = 'Optimiser le slug URL avec l\'IA';
                aiButton.style.cssText = `
                    background: #0073aa !important;
                    color: white !important;
                    border: none !important;
                    padding: 8px 12px !important;
                    margin: 8px 0 0 0 !important;
                    border-radius: 4px !important;
                    cursor: pointer !important;
                    font-size: 12px !important;
                    font-weight: 500 !important;
                    display: inline-block !important;
                    box-shadow: 0 1px 3px rgba(0,0,0,0.2) !important;
                    transition: all 0.2s ease !important;
                `;
                
                aiButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    optimizeSlug();
                });
                
                // Insérer le bouton après le champ slug
                const container = document.createElement('div');
                container.className = 'ai-slug-container';
                container.style.cssText = 'margin-top: 8px !important; display: block !important;';
                container.appendChild(aiButton);
                
                const slugContainer = slugField.closest('#edit-slug-box') || 
                                    slugField.closest('.inside') || 
                                    slugField.parentNode;
                slugContainer.insertAdjacentElement('afterend', container);
                
                slugSuccess = true;
                debugLog(' Bouton slug créé avec succès');
            } else {
                debugLog('ℹ️ Bouton slug déjà présent ou champ non trouvé');
                slugSuccess = !!document.querySelector('.ai-slug-button');
            }
        } catch (error) {
            debugLog('❌ Erreur création bouton slug:', error);
        }
        
        if (titleSuccess && slugSuccess) {
            buttonsInitialized = true;
            debugLog('🎉 Tous les boutons WooCommerce initialisés avec succès !');
            return true;
        }
        
        debugLog('⚠️ Initialisation partielle des boutons');
        return false;
    }
    
    // Optimiser le titre
    function optimizeTitle() {
        debugLog('🎯 Optimisation du titre...');
        
        const titleField = document.getElementById('title');
        if (!titleField) {
            showNotification('Champ titre non trouvé', 'error');
            return;
        }
        
        const currentTitle = titleField.value.trim();
        if (!currentTitle) {
            showNotification('Veuillez saisir un titre avant de l\'optimiser', 'warning');
            return;
        }
        
        const content = getProductContent();
        showNotification('Optimisation du titre en cours...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'ai_text_optimize_title');
        formData.append('nonce', aiTextVars.nonce);
        formData.append('current_title', currentTitle);
        formData.append('content', content);
        formData.append('post_type', aiTextVars.postType || 'product');
        
        fetch(aiTextVars.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Réponse optimisation titre:', data);
            
            if (data.success && data.data && data.data.trim() !== '') {
                titleField.value = data.data;
                titleField.focus();
                showNotification('Titre optimisé avec succès !', 'success');
                
                // Déclencher les événements
                ['input', 'change', 'blur'].forEach(eventType => {
                    titleField.dispatchEvent(new Event(eventType, { bubbles: true }));
                });
            } else {
                showNotification(data.data || 'Erreur lors de l\'optimisation du titre', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur optimisation titre:', error);
            showNotification('Erreur de connexion lors de l\'optimisation du titre', 'error');
        });
    }
    
    // Optimiser le slug
    function optimizeSlug() {
        debugLog('🔗 Optimisation du slug...');
        
        const titleField = document.getElementById('title');
        const slugField = document.getElementById('post_name') || 
                         document.querySelector('input[name="post_name"]');
        
        if (!titleField || !slugField) {
            showNotification('Champs titre ou slug non trouvés', 'error');
            return;
        }
        
        const title = titleField.value.trim();
        if (!title) {
            showNotification('Veuillez saisir un titre avant d\'optimiser le slug', 'warning');
            return;
        }
        
        const currentSlug = slugField.value.trim();
        const content = getProductContent();
        
        showNotification('Optimisation du slug en cours...', 'info');
        
        const formData = new FormData();
        formData.append('action', 'ai_text_optimize_slug');
        formData.append('nonce', aiTextVars.nonce);
        formData.append('title', title);
        formData.append('current_slug', currentSlug);
        formData.append('content', content);
        formData.append('post_type', aiTextVars.postType || 'product');
        
        fetch(aiTextVars.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            debugLog('Réponse optimisation slug:', data);
            
            if (data.success && data.data && data.data.trim() !== '') {
                slugField.value = data.data;
                slugField.focus();
                showNotification('Slug optimisé avec succès !', 'success');
                
                // Déclencher les événements
                ['input', 'change', 'blur'].forEach(eventType => {
                    slugField.dispatchEvent(new Event(eventType, { bubbles: true }));
                });
                
                // Mettre à jour l'affichage du permalien si présent
                const permalinkDisplay = document.querySelector('#sample-permalink a');
                if (permalinkDisplay) {
                    const currentHref = permalinkDisplay.href;
                    const newHref = currentHref.replace(/\/[^\/]*\/$/, '/' + data.data + '/');
                    permalinkDisplay.href = newHref;
                    permalinkDisplay.textContent = permalinkDisplay.textContent.replace(/\/[^\/]*\/$/, '/' + data.data + '/');
                }
            } else {
                showNotification(data.data || 'Erreur lors de l\'optimisation du slug', 'error');
            }
        })
        .catch(error => {
            console.error('Erreur optimisation slug:', error);
            showNotification('Erreur de connexion lors de l\'optimisation du slug', 'error');
        });
    }
    
    // Récupérer le contenu du produit
    function getProductContent() {
        let content = '';
        
        // Essayer de récupérer depuis TinyMCE
        if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
            content = tinymce.get('content').getContent({ format: 'text' });
        }
        
        // Si pas de contenu TinyMCE, essayer le textarea
        if (!content) {
            const textarea = document.getElementById('content');
            if (textarea) {
                content = textarea.value;
            }
        }
        
        // Récupérer aussi la description courte si c'est un produit
        if (aiTextVars.isProduct) {
            const shortDesc = document.getElementById('excerpt');
            if (shortDesc) {
                content = shortDesc.value + '\n\n' + content;
            }
            
            // Récupérer les attributs produit visibles
            const attributes = document.querySelectorAll('.woocommerce_attribute input, .woocommerce_attribute textarea');
            attributes.forEach(attr => {
                if (attr.value && attr.value.trim()) {
                    content += '\n' + attr.value;
                }
            });
        }
        
        return content.substring(0, 2000);
    }
    
    // Test de connexion
    function testConnection() {
        debugLog(' Test de connexion...');
        
        const formData = new FormData();
        formData.append('action', 'ai_text_test_woocommerce');
        formData.append('nonce', aiTextVars.nonce);
        
        return fetch(aiTextVars.ajaxUrl, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin'
        })
        .then(response => response.json())
        .then(data => {
            debugLog(' Résultat test connexion:', data);
            return data;
        })
        .catch(error => {
            debugLog('❌ Erreur test connexion:', error);
            return { success: false, error: error.message };
        });
    }
    
    // Initialisation principale avec stratégies multiples
    function init() {
        debugLog(' Initialisation AI Text Plugin...');
        
        if (!checkPrerequisites()) {
            debugLog('❌ Prérequis non remplis, arrêt de l\'initialisation');
            return;
        }
        
        // Stratégie d'initialisation multiple
        const initStrategies = [
            () => addWooCommerceButtons(),
            () => setTimeout(() => addWooCommerceButtons(), 500),
            () => setTimeout(() => addWooCommerceButtons(), 1000),
            () => setTimeout(() => addWooCommerceButtons(), 2000),
            () => setTimeout(() => addWooCommerceButtons(), 3000)
        ];
        
        // Exécuter toutes les stratégies
        initStrategies.forEach((strategy, index) => {
            debugLog(` Exécution stratégie ${index + 1}`);
            strategy();
        });
        
        // Initialiser TinyMCE
        waitForTinyMCE();
        
        // Écouter les événements de chargement
        if (document.readyState === 'complete') {
            addWooCommerceButtons();
        } else {
            window.addEventListener('load', addWooCommerceButtons);
        }
        
        // Support pour Gutenberg
        if (typeof wp !== 'undefined' && wp.domReady) {
            wp.domReady(function() {
                debugLog('📚 Gutenberg prêt');
                setTimeout(addWooCommerceButtons, 1000);
            });
        }
    }
    
    // Démarrage ultra-robuste
    function startup() {
        debugLog('🎬 Démarrage du plugin...');
        
        const startupMethods = [
            // Méthode 1: DOM Content Loaded
            () => {
                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', init);
                } else {
                    init();
                }
            },
            
            // Méthode 2: jQuery Ready
            () => {
                if (typeof jQuery !== 'undefined') {
                    jQuery(document).ready(init);
                }
            },
            
            // Méthode 3: setTimeout multiple
            () => setTimeout(init, 100),
            () => setTimeout(init, 500),
            () => setTimeout(init, 1000),
            () => setTimeout(init, 2000)
        ];
        
        startupMethods.forEach((method, index) => {
            try {
                method();
                debugLog(` Méthode de démarrage ${index + 1} exécutée`);
            } catch (error) {
                debugLog(`❌ Erreur méthode ${index + 1}:`, error);
            }
        });
    }
    
    // Exposition globale pour débogage - VERSION COMPLÈTE
    window.aiTextDebug = {
        // Fonctions principales
        showAIMenu: showAIMenu,
        addAIButtonToEditor: addAIButtonToEditor,
        showNotification: showNotification,
        processAIText: processAIText,
        
        // Fonctions WooCommerce
        addWooCommerceButtons: addWooCommerceButtons,
        optimizeTitle: optimizeTitle,
        optimizeSlug: optimizeSlug,
        getProductContent: getProductContent,
        
        // Utilitaires
        testConnection: testConnection,
        checkPrerequisites: checkPrerequisites,
        init: init,
        startup: startup,
        
        // Variables d'état
        editorsProcessed: editorsProcessed,
        buttonsInitialized: () => buttonsInitialized,
        
        // Debug
        debugLog: debugLog,
        setDebugMode: (mode) => { debugMode = mode; }
    };
    
    debugLog('🎯 Fonctions exposées dans window.aiTextDebug');
    
    // DÉMARRAGE AUTOMATIQUE
    startup();
    
    debugLog(' AI Text Plugin chargé avec succès - Version 2.1');
    
})();