<script>
document.addEventListener("DOMContentLoaded", function () {

    function markPostImageOrientation(image) {
        if (!image) {
            return;
        }

        const frame = image.closest(".whusup-gallery-frame");
        const slide = image.closest(".whusup-gallery-slide");

        function applyOrientationClasses() {
            if (!image.naturalWidth || !image.naturalHeight) {
                return;
            }

            const isPortrait = image.naturalHeight > image.naturalWidth;

            image.classList.toggle("mobile-portrait-image", isPortrait);

            if (frame) {
                frame.classList.toggle("mobile-portrait-frame", isPortrait);
            }

            if (slide) {
                slide.classList.toggle("mobile-portrait-slide", isPortrait);
            }
        }

        if (image.complete && image.naturalWidth) {
            applyOrientationClasses();
        } else {
            image.addEventListener("load", applyOrientationClasses, { once: true });
        }
    }

    document.querySelectorAll(".whusup-gallery-image").forEach(markPostImageOrientation);


    function updatePostImageCarousel(carousel, nextIndex) {
        const track = carousel.querySelector(".whusup-gallery-track");
        const slides = Array.from(carousel.querySelectorAll(".whusup-gallery-slide"));
        const dots = Array.from(carousel.querySelectorAll(".post-image-carousel-dot"));

        if (!track || !slides.length) {
            return;
        }

        const normalizedIndex = ((nextIndex % slides.length) + slides.length) % slides.length;

        track.style.transform = "translateX(-" + (normalizedIndex * 100) + "%)";
        carousel.dataset.currentIndex = String(normalizedIndex);

        dots.forEach(function (dot, index) {
            dot.classList.toggle("active", index === normalizedIndex);
        });
    }

    function bindPostImageCarousel(carousel) {
        if (!carousel || carousel.dataset.carouselBound === "1") {
            return;
        }

        carousel.dataset.carouselBound = "1";

        const prevButton = carousel.querySelector(".post-image-carousel-prev");
        const nextButton = carousel.querySelector(".post-image-carousel-next");
        const dots = Array.from(carousel.querySelectorAll(".post-image-carousel-dot"));

        dots.forEach(function (dot) {
            dot.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const dotIndex = parseInt(dot.dataset.dotIndex, 10) || 0;
                updatePostImageCarousel(carousel, dotIndex);
            });
        });

        let touchStartX = 0;
        let touchStartY = 0;
        let touchEndX = 0;
        let touchEndY = 0;

        if (prevButton) {
            prevButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;
                updatePostImageCarousel(carousel, currentIndex - 1);
            });
        }

        if (nextButton) {
            nextButton.addEventListener("click", function (event) {
                event.preventDefault();
                event.stopPropagation();
                const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;
                updatePostImageCarousel(carousel, currentIndex + 1);
            });
        }

        carousel.addEventListener("touchstart", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            touchStartX = event.touches[0].clientX;
            touchStartY = event.touches[0].clientY;
            touchEndX = touchStartX;
            touchEndY = touchStartY;
        }, { passive: true });

        carousel.addEventListener("touchmove", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            touchEndX = event.touches[0].clientX;
            touchEndY = event.touches[0].clientY;
        }, { passive: true });

        carousel.addEventListener("touchend", function () {
            const deltaX = touchEndX - touchStartX;
            const deltaY = touchEndY - touchStartY;

            if (Math.abs(deltaX) < 45 || Math.abs(deltaX) < Math.abs(deltaY)) {
                return;
            }

            const currentIndex = parseInt(carousel.dataset.currentIndex, 10) || 0;

            if (deltaX < 0) {
                updatePostImageCarousel(carousel, currentIndex + 1);
            } else {
                updatePostImageCarousel(carousel, currentIndex - 1);
            }
        });
    }

    document.querySelectorAll(".whusup-post-gallery").forEach(bindPostImageCarousel);


    const imageLightbox = document.getElementById("imageLightbox");
    const imageLightboxImage = document.getElementById("imageLightboxImage");
    const imageLightboxClose = document.getElementById("imageLightboxClose");
    const imageLightboxPrev = document.getElementById("imageLightboxPrev");
    const imageLightboxNext = document.getElementById("imageLightboxNext");
    const imageLightboxDots = document.getElementById("imageLightboxDots");

    let lightboxImages = [];
    let lightboxIndex = 0;
    let lightboxTouchStartX = 0;
    let lightboxTouchEndX = 0;

    function renderLightboxDots() {
        if (!imageLightboxDots) {
            return;
        }

        imageLightboxDots.innerHTML = "";

        lightboxImages.forEach(function (_, index) {
            const dot = document.createElement("button");
            dot.type = "button";
            dot.className = "image-lightbox-dot" + (index === lightboxIndex ? " active" : "");
            dot.setAttribute("aria-label", "Show image " + (index + 1));

            dot.addEventListener("click", function () {
                updateImageLightbox(index);
            });

            imageLightboxDots.appendChild(dot);
        });
    }

    function updateImageLightbox(nextIndex) {
        if (!imageLightbox || !imageLightboxImage || !lightboxImages.length) {
            return;
        }

        lightboxIndex = ((nextIndex % lightboxImages.length) + lightboxImages.length) % lightboxImages.length;
        imageLightboxImage.src = lightboxImages[lightboxIndex];

        imageLightbox.classList.toggle("has-multiple", lightboxImages.length > 1);
        imageLightbox.dataset.currentIndex = String(lightboxIndex);

        renderLightboxDots();
    }

    function openImageLightbox(images, startIndex) {
        if (!imageLightbox || !imageLightboxImage) {
            return;
        }

        lightboxImages = Array.isArray(images) ? images.filter(Boolean) : [images].filter(Boolean);

        if (!lightboxImages.length) {
            return;
        }

        lightboxIndex = startIndex || 0;
        imageLightbox.classList.add("open");
        imageLightbox.setAttribute("aria-hidden", "false");
        document.body.classList.add("lightbox-open");

        updateImageLightbox(lightboxIndex);
    }

    function closeImageLightbox() {
        if (!imageLightbox || !imageLightboxImage) {
            return;
        }

        imageLightbox.classList.remove("open", "has-multiple");
        imageLightbox.setAttribute("aria-hidden", "true");
        imageLightboxImage.src = "";
        document.body.classList.remove("lightbox-open");
        lightboxImages = [];
        lightboxIndex = 0;

        if (imageLightboxDots) {
            imageLightboxDots.innerHTML = "";
        }
    }

    function bindPostImageLightbox(image) {
        if (!image || image.dataset.lightboxBound === "1") {
            return;
        }

        image.dataset.lightboxBound = "1";

        image.addEventListener("click", function () {
            const carousel = image.closest(".whusup-post-gallery");
            const allImages = carousel
                ? Array.from(carousel.querySelectorAll(".whusup-gallery-image")).map(function (img) {
                    return img.dataset.fullImage || img.currentSrc || img.src;
                }).filter(Boolean)
                : [image.dataset.fullImage || image.currentSrc || image.src].filter(Boolean);

            const imageIndex = parseInt(image.dataset.imageIndex, 10) || 0;
            openImageLightbox(allImages, imageIndex);
        });
    }

    function bindProfileLightbox(image) {
        if (!image || image.dataset.lightboxBound === "1") {
            return;
        }

        image.dataset.lightboxBound = "1";

        image.addEventListener("click", function (event) {
            event.stopPropagation();

            openImageLightbox([
                image.dataset.fullImage || image.currentSrc || image.src
            ], 0);
        });
    }

    document.querySelectorAll(".whusup-gallery-image").forEach(function (image) {
        bindPostImageLightbox(image);
    });
    document.querySelectorAll(".profile-expandable").forEach(bindProfileLightbox);

    if (imageLightboxPrev) {
        imageLightboxPrev.addEventListener("click", function (event) {
            event.stopPropagation();
            updateImageLightbox(lightboxIndex - 1);
        });
    }

    if (imageLightboxNext) {
        imageLightboxNext.addEventListener("click", function (event) {
            event.stopPropagation();
            updateImageLightbox(lightboxIndex + 1);
        });
    }

    if (imageLightboxClose) {
        imageLightboxClose.addEventListener("click", closeImageLightbox);
    }

    if (imageLightbox) {
        imageLightbox.addEventListener("click", function (event) {
            if (event.target === imageLightbox) {
                closeImageLightbox();
            }
        });

        imageLightbox.addEventListener("touchstart", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            lightboxTouchStartX = event.touches[0].clientX;
            lightboxTouchEndX = lightboxTouchStartX;
        }, { passive: true });

        imageLightbox.addEventListener("touchmove", function (event) {
            if (!event.touches || event.touches.length === 0) {
                return;
            }

            lightboxTouchEndX = event.touches[0].clientX;
        }, { passive: true });

        imageLightbox.addEventListener("touchend", function () {
            const deltaX = lightboxTouchEndX - lightboxTouchStartX;

            if (Math.abs(deltaX) < 45 || lightboxImages.length <= 1) {
                return;
            }

            if (deltaX < 0) {
                updateImageLightbox(lightboxIndex + 1);
            } else {
                updateImageLightbox(lightboxIndex - 1);
            }
        });
    }

    document.addEventListener("keydown", function (event) {
        if (!imageLightbox || !imageLightbox.classList.contains("open")) {
            return;
        }

        if (event.key === "Escape") {
            closeImageLightbox();
        }

        if (event.key === "ArrowLeft" && lightboxImages.length > 1) {
            updateImageLightbox(lightboxIndex - 1);
        }

        if (event.key === "ArrowRight" && lightboxImages.length > 1) {
            updateImageLightbox(lightboxIndex + 1);
        }
    });

    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text ?? "";
        return div.innerHTML;
    }

    function linkifyEscapedText(text) {
        const escaped = escapeHtml(text);

        return escaped
            .replace(/(https?:\/\/[^\s<]+)/gi, function (url) {
                let display = url.replace(/^https?:\/\//i, "").replace(/\/$/, "");

                if (display.length > 40) {
                    display = display.substring(0, 37) + "...";
                }

                return '<a href="' + url + '" target="_blank" rel="noopener noreferrer">' + display + '</a>';
            })
            .replace(/\n/g, "<br>");
    }

    function showAjaxMessage(form, message) {
        const box = form.querySelector(".ajax-comment-message");

        if (!box) return;

        box.textContent = message;
        box.style.display = "block";
    }

    function updateCommentCount(postCard, amount) {
        const countSpan = postCard.querySelector(".comment-count");

        if (!countSpan) return;

        const currentCount = parseInt(countSpan.textContent, 10) || 0;
        const newCount = Math.max(0, currentCount + amount);

        countSpan.textContent = newCount;

        const noCommentsText = postCard.querySelector(".no-comments-text");

        if (noCommentsText) {
            noCommentsText.style.display = newCount === 0 ? "" : "none";
        }
    }

    function getReplyButtonText(count, isOpen) {
        const label = count === 1 ? "reply" : "replies";
        return isOpen ? "Hide replies" : "View " + count + " " + label;
    }

    function refreshReplyToggle(button, count, isOpen) {
        button.dataset.count = String(count);
        button.textContent = getReplyButtonText(count, isOpen);
    }

    function attachReplyCollapseToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.dataset.target;
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");

            const isOpen = section.classList.contains("open");
            const count = parseInt(button.dataset.count, 10) || section.children.length || 0;

            refreshReplyToggle(button, count, isOpen);
        });
    }

    function ensureReplyContainer(parentCommentBox) {
        const parentCommentId = parentCommentBox.dataset.commentId;
        let children = parentCommentBox.querySelector(":scope > .comment-children");

        if (!children) {
            children = document.createElement("div");
            children.id = "comment-children-" + parentCommentId;
            children.className = "comment-children collapsed-replies";
            parentCommentBox.appendChild(children);
        }

        let toggle = parentCommentBox.querySelector(":scope > .reply-collapse-toggle");

        if (!toggle) {
            toggle = document.createElement("button");
            toggle.type = "button";
            toggle.className = "reply-collapse-toggle";
            toggle.dataset.target = children.id;
            toggle.dataset.count = "0";
            toggle.textContent = "View replies";
            parentCommentBox.insertBefore(toggle, children);
            attachReplyCollapseToggle(toggle);
        }

        children.classList.add("open");
        children.classList.add("collapsed-replies");

        const newCount = children.querySelectorAll(":scope > .comment-box").length + 1;
        refreshReplyToggle(toggle, newCount, true);

        return children;
    }

    function attachReplyToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.dataset.target;
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");
        });
    }

    function attachEditToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-target");
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");

            button.textContent = section.classList.contains("open")
                ? "Cancel"
                : "Edit";
        });
    }

    function attachCommentLikeIndicator(indicator) {
        if (!indicator || indicator.dataset.bound === "1") return;

        indicator.dataset.bound = "1";

        indicator.addEventListener("click", function () {
            const loggedIn = indicator.dataset.loggedIn === "1";

            if (!loggedIn) {
                return;
            }

            const commentId = indicator.dataset.commentId;
            const countSpan = indicator.querySelector(".comment-like-count");

            fetch("/ajax/toggle_comment_like.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "comment_id=" + encodeURIComponent(commentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    console.error("Comment like failed:", data);
                    return;
                }

                if (countSpan) {
                    countSpan.textContent = data.like_count;
                }

                if (data.liked) {
                    indicator.classList.add("liked");
                } else {
                    indicator.classList.remove("liked");
                }
            })
            .catch(function (error) {
                console.error("Comment like error:", error);
            });
        });
    }

    function attachEditPostForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const postCard = form.closest(".recent-post-card");
            const postId = form.querySelector('input[name="post_id"]').value;
            const textarea = form.querySelector('textarea[name="post_body"]');
            const postBody = textarea.value.trim();

            if (postBody === "") {
                alert("Post cannot be empty.");
                return;
            }

            fetch("/ajax/edit_post.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&post_body=" + encodeURIComponent(postBody)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Edit post failed.");
                    return;
                }

                const postBodyDiv = postCard.querySelector(".recent-post-body");
                const editSection = form.closest(".edit-post-section");
                const editButton = postCard.querySelector(".edit-toggle-button");

                postBodyDiv.innerHTML = linkifyEscapedText(postBody);
                editSection.classList.remove("open");

                if (editButton) {
                    editButton.textContent = "Edit";
                }
            })
            .catch(function (error) {
                console.error("Edit post error:", error);
                alert("Edit post failed.");
            });
        });
    }

    function attachEditCommentForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const commentId = form.querySelector('input[name="comment_id"]').value;
            const textarea = form.querySelector('textarea[name="comment_body"]');
            const commentBody = textarea.value.trim();

            if (commentBody === "") {
                alert("Comment cannot be empty.");
                return;
            }

            fetch("/ajax/edit_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "comment_id=" + encodeURIComponent(commentId) +
                    "&comment_body=" + encodeURIComponent(commentBody)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Edit failed.");
                    return;
                }

                const commentBox = form.closest(".comment-box");
                const commentBodyDiv = commentBox.querySelector(".comment-body");
                const editSection = form.closest(".edit-comment-section");
                const toggleButton = commentBox.querySelector(".edit-comment-toggle-button");

                commentBodyDiv.innerHTML = linkifyEscapedText(commentBody);
                editSection.classList.remove("open");

                if (toggleButton) {
                    toggleButton.textContent = "Edit";
                }
            })
            .catch(function (error) {
                console.error("Edit comment error:", error);
                alert("Edit failed.");
            });
        });
    }

    function attachDeleteCommentButton(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            if (!confirm("Are you sure you want to delete this comment?")) {
                return;
            }

            const commentId = button.dataset.commentId;
            const commentBox = button.closest(".comment-box");
            const parentCommentBox = commentBox.parentElement?.closest(".comment-box");
            const postCard = button.closest(".recent-post-card");

            fetch("/ajax/delete_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "comment_id=" + encodeURIComponent(commentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    alert(data.message || "Delete failed.");
                    return;
                }

                const nestedCount = commentBox.querySelectorAll(".comment-box").length;
                const removedCount = nestedCount + 1;

                commentBox.remove();
                updateCommentCount(postCard, -removedCount);

                if (parentCommentBox) {
                    const children = parentCommentBox.querySelector(":scope > .comment-children");
                    const toggle = parentCommentBox.querySelector(":scope > .reply-collapse-toggle");

                    if (children && toggle) {
                        const childCount = children.querySelectorAll(":scope > .comment-box").length;

                        if (childCount <= 0) {
                            toggle.remove();
                            children.classList.remove("collapsed-replies", "open");
                        } else {
                            refreshReplyToggle(toggle, childCount, children.classList.contains("open"));
                        }
                    }
                }
            })
            .catch(function (error) {
                console.error("Delete comment error:", error);
                alert("Delete failed.");
            });
        });
    }

    function createCommentBoxHtml(commentId, postId, parentCommentId, commentBody, name) {
        return `
            <div class="comment-name">${escapeHtml(name)}</div>

            <div class="comment-date">Just now</div>

            <div class="comment-body">${linkifyEscapedText(commentBody)}</div>

            <div class="comment-owner-actions">
                <span
                    class="like-indicator comment-like-indicator ajax-comment-like-indicator can-like"
                    data-comment-id="${commentId}"
                    data-logged-in="1"
                    title="Like comment"
                >
                    <span class="like-icon">
                        <svg viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M7 10v10H4V10h3zm3 10h7.4c.9 0 1.7-.6 1.9-1.5l1.4-6.2c.3-1.2-.6-2.3-1.9-2.3h-5.1l.8-3.8c.1-.6-.1-1.2-.5-1.6L13.2 4 9 9.1V20z"/>
                        </svg>
                    </span>
                    <span class="like-count comment-like-count">0</span>
                </span>

                <button
                    type="button"
                    class="comment-action-link comment-reply-link reply-toggle-button"
                    data-target="reply-form-${commentId}"
                >
                    Reply
                </button>

                <button 
                    type="button" 
                    class="comment-action-link comment-edit-link edit-comment-toggle-button"
                    data-target="edit-comment-${commentId}"
                >
                    Edit
                </button>

                <button 
                    type="button" 
                    class="comment-action-link comment-delete-link ajax-delete-comment-button"
                    data-comment-id="${commentId}"
                >
                    Delete
                </button>
            </div>

            <div id="edit-comment-${commentId}" class="edit-comment-section">
                <form class="edit-comment-form ajax-edit-comment-form">
                    <input type="hidden" name="comment_id" value="${commentId}">
                    <textarea name="comment_body">${escapeHtml(commentBody)}</textarea>

                    <button type="submit" class="recent-post-button small-action-button">
                        Save
                    </button>
                </form>
            </div>

            <div id="reply-form-${commentId}" class="reply-form-section">
                <form class="comment-form ajax-add-comment-form">
                    <input type="hidden" name="post_id" value="${postId}">
                    <input type="hidden" name="parent_comment_id" value="${commentId}">

                    <textarea name="comment_body" placeholder="Write a reply..."></textarea>

                    <button type="submit" class="recent-post-button small-action-button">
                        Reply
                    </button>

                    <div class="ajax-comment-message"></div>
                </form>
            </div>

            <div id="comment-children-${commentId}" class="comment-children"></div>
        `;
    }

    function bindCommentBox(commentBox) {
        attachCommentLikeIndicator(commentBox.querySelector(".ajax-comment-like-indicator"));
        attachReplyToggle(commentBox.querySelector(".reply-toggle-button"));
        attachEditToggle(commentBox.querySelector(".edit-comment-toggle-button"));
        attachEditCommentForm(commentBox.querySelector(".ajax-edit-comment-form"));
        attachDeleteCommentButton(commentBox.querySelector(".ajax-delete-comment-button"));
        attachAddCommentForm(commentBox.querySelector(".ajax-add-comment-form"));
        attachReplyCollapseToggle(commentBox.querySelector(".reply-collapse-toggle"));
    }

    function attachAddCommentForm(form) {
        if (!form || form.dataset.bound === "1") return;

        form.dataset.bound = "1";

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            const postCard = form.closest(".recent-post-card");
            const parentCommentBox = form.closest(".comment-box");
            const postId = form.querySelector('input[name="post_id"]').value;
            const parentCommentId = form.querySelector('input[name="parent_comment_id"]')?.value || "";
            const textarea = form.querySelector('textarea[name="comment_body"]');
            const commentBody = textarea.value.trim();

            if (commentBody === "") {
                showAjaxMessage(form, "Comment cannot be empty.");
                return;
            }

            fetch("/ajax/add_comment.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body:
                    "post_id=" + encodeURIComponent(postId) +
                    "&comment_body=" + encodeURIComponent(commentBody) +
                    "&parent_comment_id=" + encodeURIComponent(parentCommentId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    showAjaxMessage(form, data.message || "Comment failed.");
                    return;
                }

                const commentId = data.comment_id;
                const isReply = parentCommentId !== "";

                const commentBox = document.createElement("div");
                commentBox.className = isReply ? "comment-box comment-reply" : "comment-box";
                commentBox.dataset.commentId = commentId;
                commentBox.dataset.parentCommentId = parentCommentId;
                commentBox.innerHTML = createCommentBoxHtml(commentId, postId, parentCommentId, commentBody, data.name || "You");

                if (isReply && parentCommentBox) {
                    const children = ensureReplyContainer(parentCommentBox);
                    children.appendChild(commentBox);

                    const replySection = form.closest(".reply-form-section");

                    if (replySection) {
                        replySection.classList.remove("open");
                    }
                } else {
                    const commentList = postCard.querySelector(".comment-list");
                    commentList.appendChild(commentBox);
                }

                bindCommentBox(commentBox);

                textarea.value = "";

                const messageBox = form.querySelector(".ajax-comment-message");
                if (messageBox) {
                    messageBox.style.display = "none";
                    messageBox.textContent = "";
                }

                updateCommentCount(postCard, 1);
            })
            .catch(function (error) {
                console.error("Add comment error:", error);
                showAjaxMessage(form, "Comment failed.");
            });
        });
    }

    function attachPostLikeIndicator(indicator) {
        if (!indicator || indicator.dataset.bound === "1") return;

        indicator.dataset.bound = "1";

        indicator.addEventListener("click", function () {
            const loggedIn = indicator.dataset.loggedIn === "1";

            if (!loggedIn) {
                return;
            }

            const postId = indicator.dataset.postId;
            const countSpan = indicator.querySelector(".like-count");

            fetch("/ajax/toggle_like.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/x-www-form-urlencoded"
                },
                body: "post_id=" + encodeURIComponent(postId)
            })
            .then(response => response.json())
            .then(function (data) {
                if (!data.success) {
                    console.error("Like failed:", data);
                    return;
                }

                countSpan.textContent = data.like_count;

                if (data.liked) {
                    indicator.classList.add("liked");
                } else {
                    indicator.classList.remove("liked");
                }
            })
            .catch(function (error) {
                console.error("Like error:", error);
            });
        });
    }

    document.querySelectorAll(".ajax-like-indicator").forEach(attachPostLikeIndicator);

    function attachCommentIconToggle(button) {
        if (!button || button.dataset.bound === "1") return;

        button.dataset.bound = "1";

        button.addEventListener("click", function () {
            const targetId = button.getAttribute("data-target");
            const section = document.getElementById(targetId);

            if (!section) return;

            section.classList.toggle("open");
        });
    }

    document.querySelectorAll(".comment-icon-toggle").forEach(attachCommentIconToggle);

    /*
     * Record a view only after at least 50% of a post card has remained
     * visible for two continuous seconds.
     *
     * One shared observer handles both initial cards and cards added later
     * through infinite scrolling.
     */
    const postViewTimers = new WeakMap();

    const postViewObserver = "IntersectionObserver" in window
        ? new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                const postCard = entry.target;

                if (
                    !postCard ||
                    postCard.dataset.viewTrackingComplete === "1"
                ) {
                    postViewObserver.unobserve(postCard);
                    return;
                }

                if (entry.isIntersecting && entry.intersectionRatio >= 0.75) {
                    if (postViewTimers.has(postCard)) {
                        return;
                    }

                    const timer = window.setTimeout(function () {
                        postViewTimers.delete(postCard);

                        if (
                            document.hidden ||
                            postCard.dataset.viewTrackingComplete === "1" ||
                            postCard.dataset.viewRequestPending === "1"
                        ) {
                            return;
                        }

                        const postId = String(
                            postCard.dataset.postId || ""
                        ).trim();

                        if (!/^\d+$/.test(postId) || postId === "0") {
                            postCard.dataset.viewTrackingComplete = "1";
                            postViewObserver.unobserve(postCard);
                            return;
                        }

                        postCard.dataset.viewRequestPending = "1";

                        fetch("/ajax/post_view.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                                "Accept": "application/json"
                            },
                            credentials: "same-origin",
                            cache: "no-store",
                            body: JSON.stringify({
                                post_id: parseInt(postId, 10)
                            })
                        })
                        .then(function (response) {
                            if (!response.ok) {
                                throw new Error(
                                    "Post view request failed with status "
                                    + response.status
                                );
                            }

                            return response.json();
                        })
                        .then(function (data) {
                            if (!data || data.success !== true) {
                                throw new Error(
                                    data && data.message
                                        ? data.message
                                        : "Post view request was unsuccessful."
                                );
                            }

                            const numericCount = Number.parseInt(
                                data.view_count,
                                10
                            );

                            if (Number.isFinite(numericCount)) {
                                const formattedCount =
                                    numericCount.toLocaleString();

                                const countElement = postCard.querySelector(
                                    ".post-view-count"
                                );

                                if (countElement) {
                                    countElement.textContent = formattedCount;
                                }

                                const indicator = postCard.querySelector(
                                    ".post-view-indicator"
                                );

                                if (indicator) {
                                    const viewLabel =
                                        formattedCount
                                        + (numericCount === 1
                                            ? " view"
                                            : " views");

                                    indicator.title = viewLabel;
                                    indicator.setAttribute(
                                        "aria-label",
                                        viewLabel
                                    );
                                }
                            }

                            /*
                             * A recent duplicate or owner view is still a
                             * completed result, so do not request it again
                             * during this page load.
                             */
                            postCard.dataset.viewTrackingComplete = "1";
                            postViewObserver.unobserve(postCard);
                        })
                        .catch(function (error) {
                            /*
                             * Allow another attempt if the request failed due
                             * to a temporary network or server problem.
                             */
                            delete postCard.dataset.viewRequestPending;

                            console.error(
                                "Post view tracking error:",
                                error
                            );
                        })
                        .finally(function () {
                            if (
                                postCard.dataset.viewTrackingComplete === "1"
                            ) {
                                delete postCard.dataset.viewRequestPending;
                            }
                        });
                    }, 2000);

                    postViewTimers.set(postCard, timer);
                    return;
                }

                const activeTimer = postViewTimers.get(postCard);

                if (activeTimer) {
                    window.clearTimeout(activeTimer);
                    postViewTimers.delete(postCard);
                }
            });
        }, {
            threshold: [0, 0.75, 1]
        })
        : null;

    function observePostView(postCard) {
        if (
            !postCard ||
            !postViewObserver ||
            postCard.dataset.viewObserverBound === "1"
        ) {
            return;
        }

        postCard.dataset.viewObserverBound = "1";
        postViewObserver.observe(postCard);
    }

    document.addEventListener("visibilitychange", function () {
        if (!document.hidden) {
            return;
        }

        document.querySelectorAll(
            '.recent-post-card[data-view-observer-bound="1"]'
        ).forEach(function (postCard) {
            const activeTimer = postViewTimers.get(postCard);

            if (activeTimer) {
                window.clearTimeout(activeTimer);
                postViewTimers.delete(postCard);
            }
        });
    });

    function initializeRecentPostCard(postCard) {
        if (!postCard) return;

        observePostView(postCard);

        postCard.querySelectorAll(".whusup-gallery-image").forEach(function (image) {
            markPostImageOrientation(image);
                bindPostImageLightbox(image);
        });

        postCard.querySelectorAll(".whusup-post-gallery").forEach(bindPostImageCarousel);
        postCard.querySelectorAll(".profile-expandable").forEach(bindProfileLightbox);
        postCard.querySelectorAll(".ajax-like-indicator").forEach(attachPostLikeIndicator);
        postCard.querySelectorAll(".comment-icon-toggle").forEach(attachCommentIconToggle);
        postCard.querySelectorAll(".reply-collapse-toggle").forEach(attachReplyCollapseToggle);
        postCard.querySelectorAll(".reply-toggle-button").forEach(attachReplyToggle);
        postCard.querySelectorAll(".edit-toggle-button").forEach(attachEditToggle);
        postCard.querySelectorAll(".edit-comment-toggle-button").forEach(attachEditToggle);
        postCard.querySelectorAll(".ajax-comment-like-indicator").forEach(attachCommentLikeIndicator);
        postCard.querySelectorAll(".ajax-edit-post-form").forEach(attachEditPostForm);
        postCard.querySelectorAll(".ajax-edit-comment-form").forEach(attachEditCommentForm);
        postCard.querySelectorAll(".ajax-delete-comment-button").forEach(attachDeleteCommentButton);
        postCard.querySelectorAll(".ajax-add-comment-form").forEach(attachAddCommentForm);
    }

    function initializeInfiniteScroll() {
        const postsList = document.getElementById("recent-posts-list");
        const loadingBox = document.getElementById("recent-posts-loading");
        const endBox = document.getElementById("recent-posts-end");

        if (!postsList) return;

        let isLoading = false;
        let hasMore = postsList.dataset.hasMore === "1";

        function setLoading(isActive) {
            isLoading = isActive;
            if (loadingBox) {
                loadingBox.style.display = isActive ? "block" : "none";
            }
        }

        function maybeShowEnd() {
            if (!hasMore && endBox && postsList.querySelectorAll(".recent-post-card").length > 0) {
                endBox.style.display = "block";
            }
        }

        function loadMorePosts() {
            if (isLoading || !hasMore) return;

            const scrollPosition = window.innerHeight + window.scrollY;
            const triggerPosition = document.documentElement.scrollHeight - 700;

            if (scrollPosition < triggerPosition) return;

            setLoading(true);

            const params = new URLSearchParams();
            params.set("offset", postsList.dataset.nextOffset || "0");
            params.set("sort", postsList.dataset.sort || "recent");
            params.set("filter", postsList.dataset.filter || "all");

            if (postsList.dataset.tag) {
                params.set("tag", postsList.dataset.tag);
            }

            fetch("/ajax/load_feed.php?" + params.toString(), {
                method: "GET",
                headers: {
                    "Accept": "application/json"
                }
            })
            .then(function (response) {
                return response.json();
            })
            .then(function (data) {
                if (!data.success) {
                    console.error("Load more posts failed:", data);
                    hasMore = false;
                    postsList.dataset.hasMore = "0";
                    maybeShowEnd();
                    return;
                }

                const temp = document.createElement("div");
                temp.innerHTML = data.html || "";

                const newCards = Array.from(temp.querySelectorAll(".recent-post-card"));

                newCards.forEach(function (card) {
                    postsList.appendChild(card);
                    initializeRecentPostCard(card);
                });

                postsList.dataset.nextOffset = String(data.next_offset || postsList.querySelectorAll(".recent-post-card").length);
                hasMore = data.has_more === true || data.has_more === 1 || data.has_more === "1";
                postsList.dataset.hasMore = hasMore ? "1" : "0";

                maybeShowEnd();
            })
            .catch(function (error) {
                console.error("Load more posts error:", error);
            })
            .finally(function () {
                setLoading(false);
            });
        }

        window.addEventListener("scroll", loadMorePosts, { passive: true });
        window.addEventListener("resize", loadMorePosts);
        loadMorePosts();
    }

    document.querySelectorAll(".reply-collapse-toggle").forEach(attachReplyCollapseToggle);
    document.querySelectorAll(".reply-toggle-button").forEach(attachReplyToggle);
    document.querySelectorAll(".edit-toggle-button").forEach(attachEditToggle);
    document.querySelectorAll(".edit-comment-toggle-button").forEach(attachEditToggle);
    document.querySelectorAll(".ajax-comment-like-indicator").forEach(attachCommentLikeIndicator);
    document.querySelectorAll(".ajax-edit-post-form").forEach(attachEditPostForm);
    document.querySelectorAll(".ajax-edit-comment-form").forEach(attachEditCommentForm);
    document.querySelectorAll(".ajax-delete-comment-button").forEach(attachDeleteCommentButton);
    document.querySelectorAll(".ajax-add-comment-form").forEach(attachAddCommentForm);

    document.querySelectorAll(".recent-post-card").forEach(initializeRecentPostCard);
    initializeInfiniteScroll();
});
</script>
