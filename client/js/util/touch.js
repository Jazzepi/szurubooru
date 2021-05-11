"use strict";

const direction = {
    NONE: null,
    LEFT: "left",
    RIGHT: "right",
};

function handleTouchStart(handler, evt) {
    const touchEvent = evt.touches[0];
    handler._xStart = touchEvent.clientX;
    handler._multiTouches = false;
}

function handleTouchMove(handler, evt) {
    if ( window.visualViewport.width < $('.post-content').innerWidth() ) {
        $('.classicButtons').css('opacity',0)
    }else{
        $('.classicButtons').css('opacity',1)
    }
    if (!handler._xStart) {
        return;
    }
    if (evt.touches.length > 1) {
        handler._multiTouches = true;
        return handler._direction = direction.NONE;
    }
    if (evt.touches[0].clientX - handler._xStart > window.innerWidth / 4) {
        handler._direction = direction.LEFT;
    } else if (handler._xStart - evt.touches[0].clientX > window.innerWidth / 4) {
        handler._direction = direction.RIGHT;
    }
}

function handleTouchEnd(handler, evt) {
    if ($('.fit-original').hasClass('active') || handler._multiTouches) return 
    let contentWidth = $('.content').innerWidth()
    if ( window.visualViewport.width < contentWidth ) return
    switch (handler._direction) {
        case direction.NONE:
            return;
        case direction.LEFT:
            handler._swipeLeftTask();
            break;
        case direction.RIGHT:
            handler._swipeRightTask();
            break;
    }
    handler._xStart = null;
}

class Touch {
    constructor(
        target,
        swipeLeft = () => { },
        swipeRight = () => { },
    ) {
        this._target = target;

        this._swipeLeftTask = swipeLeft;
        this._swipeRightTask = swipeRight;

        this._xStart = null;

        this._multiTouches = false;

        this._direction = direction.NONE;

        this._swipeDisabled = false

        this._target.addEventListener("touchstart", (evt) => {
            handleTouchStart(this, evt);
        });
        this._target.addEventListener("touchmove", (evt) => {
            handleTouchMove(this, evt);
        });
        this._target.addEventListener("touchend", (evt) => {
            handleTouchEnd(this, evt);
        });
    }
}

module.exports = Touch;
