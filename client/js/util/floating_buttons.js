'use-strict';

class floatingButtons {
    constructor() {
        this.container = $("#floatingButtons")
        this.classicButtons = $(".classicButtons")
        this.pendingUpdate = false
        this.moveTimeout
        this.moveTimeoutMs = 3000
        this.floatingHeight = .07
        this.init()
    }

    init() {
        this.viewportHandler()
        window.visualViewport.addEventListener('scroll', (e) => {
            this.viewportHandler(e)
        })
        window.visualViewport.addEventListener('resize', (e) => {
            this.viewportHandler(e)
        })
        document.addEventListener("touchstart", (evt) => {
            this.touchShow()
        });

    }

    touchShow() {
        window.clearTimeout(this.moveTimeout)
        let contentWidth = $('.post-content').innerWidth()
        if (!$('.fit-original').hasClass('active') && window.visualViewport.width > contentWidth) return this.hideFloat()
        this.showFloat()
        this.moveTimeout = setTimeout(() => { this.hideFloat() }, this.moveTimeoutMs);
    }

    moveShow() {
        window.clearTimeout(this.moveTimeout)
        let contentWidth = $('.post-content').innerWidth()
        if (!$('.fit-original').hasClass('active') && window.visualViewport.width > contentWidth) return this.hideFloat()
        this.hideFloat()
        this.moveTimeout = setTimeout(() => {
            this.showFloat()
            this.moveTimeout = setTimeout(() => { this.hideFloat() }, this.moveTimeoutMs);
        }, 100);
    }

    showFloat() {
        this.container.css({
            'opacity': 1,
            'pointer-events': 'initial'
        })
    }

    hideFloat() {
        this.container.css({
            'opacity': 0,
            'pointer-events': 'none'
        })
    }

    viewportHandler() {
        if (this.pendingUpdate) return;
        this.pendingUpdate = true;
        this.moveShow()
        requestAnimationFrame(()=>{
            this.pendingUpdate = false;
            let viewportHeight = window.visualViewport.height,
                viewportWidth = window.visualViewport.width,
                offsetLeft = window.visualViewport.offsetLeft,
                offsetTop = window.visualViewport.offsetTop,
                offsetBot = window.visualViewport.offsetTop + viewportHeight
            // console.log({offsetLeft,offsetTop,offsetBot,viewportHeight,viewportWidth})
            this.container.css({
                'top': offsetBot + "px",
                'left': offsetLeft + "px",
                'width': viewportWidth + "px",
                'height': viewportHeight * this.floatingHeight + "px"
            })

        })
    }
}

module.exports = floatingButtons