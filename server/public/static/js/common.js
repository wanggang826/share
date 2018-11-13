/*------------------------ cookie ---------------------------*/
// 设置Cookie
function setCookie(name,value,expires_seconds, encode){
    if (encode) {
        value = encodeURI(value);
    }
    var cookieString = name + "=" + value;
    //判断是否设置过期时间
    if(expires_seconds > 0){
        var date=new Date();
        date.setTime(date.getTime() + expires_seconds * 1000);
        cookieString += "; expires=" + date.toGMTString() + "; path=/";
    }
    document.cookie = cookieString;
}

// 获取Cookie
function getCookie(name, decode){
    var strCookie=document.cookie;
    var arrCookie=strCookie.split("; ");
    for(var i=0;i<arrCookie.length;i++){
        var arr=arrCookie[i].split("=");
        if(arr[0]==name) {
            if (decode) {
                return decodeURI(arr[1]);
            }
            return arr[1];
        };
    }
    return "";
}

// 删除Cookie
function delCookie(name){
    var date = new Date();
    date.setTime( date.getTime() - 10000 );
    document.cookie = name + "=v; expires=" + date.toGMTString();
}

// 千米数取整
function distanceUnit(meter) {
    if(meter < 1000) {
        return Math.ceil(meter) + "m"; //取整
    } else {
        meter = (meter / 1000).toFixed(1);
        return (meter > 1 ? '>999' : meter) + "km";
    }
}

// 定位脚本执行时间
var start_time = new Date().getTime();
function time_debug(stime,marker){
    var time_now = new Date().getTime();
    var msg = marker + " : " + (time_now - stime) + "ms";
    debug(msg);
    return msg;
}

// 调试输出
var DEBUG = false; // 全局变量　控制是否输出日志
function debug(msg){
    if(DEBUG){
        if(Object.prototype.toString.call(msg) === "[object String]")
        {
            document.getElementById("debug").append("<li>"+msg+"</li>");
        }else{
            for(var k in msg){
                debug("----------" + k + "----------");
                debug(msg[k]);
                debug("----------" + k + "----------");
            }
        }
    }
}

// 判断是否微信浏览器
function isWeiXin(){
    var ua = window.navigator.userAgent.toLowerCase();
    if(ua.match(/MicroMessenger/i) == 'micromessenger'){
        return true;
    }else{
        return false;
    }
}

// 判断是否为支付宝浏览器
function isAlipay(){
    var ua = window.navigator.userAgent.toLowerCase();
    if(ua.match(/alipay/i) == 'alipay'){
        return true;
    }else{
        return false;
    }
}

// 判断变量是否存在
function isset(a){
    try {
        if (typeof(a) == "undefined") {
            //alert("value is undefined");
            return false;
        } else {
            //alert("value is true");
            return true;
        }
    } catch(e) {}
    return false;
}

// 判断函数的存在
function function_exists(funcName) {
    try {
        if (typeof(eval(funcName)) == "function") {
            return true;
        }
    } catch(e) {}
    return false;
}


function wxApiCloseWindow() {
    WeixinJSBridge.invoke('closeWindow',{},function(res){
        if(res.err_msg == "close_window:error") {
            alert("关闭微信网页错误，请稍后重试，谢谢");
        }
    });
}

function alipayCloseWindow() {
    AlipayJSBridge.call('exitApp');
}

function callCloseWindow() {
    if (typeof WeixinJSBridge == "undefined"){
        if( document.addEventListener ){
            document.addEventListener('WeixinJSBridgeReady', wxApiCloseWindow, false);
        }else if (document.attachEvent){
            document.attachEvent('WeixinJSBridgeReady', wxApiCloseWindow);
            document.attachEvent('onWeixinJSBridgeReady', wxApiCloseWindow);
        }
    }else{
        wxApiCloseWindow();
        return;
    }

    if (typeof AlipayJSBridge == "undefined"){
        if( document.addEventListener ){
            document.addEventListener('AlipayJSBridgeReady', alipayCloseWindow, false);
        }else if (document.attachEvent){
            document.attachEvent('AlipayJSBridgeReady', alipayCloseWindow);
            document.attachEvent('onAlipayJSBridgeReady', alipayCloseWindow);
        }
    }else{
        alipayCloseWindow();
    }
}