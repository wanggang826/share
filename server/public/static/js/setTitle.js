/**
 * 解决微信iOS端设置title
 * @param {String} title
 */
export var SetTitle = function (title) {
  document.title = title
  const mobile = navigator.userAgent.toLowerCase()
  if (/iphone|ipad|ipod/.test(mobile)) {
    const iframe = document.createElement('iframe')
    iframe.style.visibility = 'hidden'
    // iframe.setAttribute('src', 'static/nearby_loading.png')
    const iframeCallback = () => {
      const timer = setTimeout(() => {
        iframe.removeEventListener('load', iframeCallback)
        document.body.removeChild(iframe)
        clearTimeout(timer)
      }, 0)
    }
    iframe.addEventListener('load', iframeCallback)
    document.body.appendChild(iframe)
  }
}

