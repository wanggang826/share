webpackJsonp([1],{CTFd:function(t,e){},"Eh/m":function(t,e){},I44A:function(t,e){},KhLB:function(t,e){},NHnr:function(t,e,s){"use strict";function a(t){s("gG2Y")}function i(t){s("hqUG")}function o(t){s("I44A")}function n(t){s("Eh/m")}function r(t){s("pN4k")}function c(t){s("CTFd")}function l(t){s("lbjU")}function h(t){s("KhLB")}function d(t){s("eGlH")}function u(t){s("P/iQ")}Object.defineProperty(e,"__esModule",{value:!0});var m=s("TWX9"),p=(s("3cXf"),s("rVsN")),_=s.n(p),w=s("2sCs"),g=s.n(w),f=s("DEjr"),v=s.n(f),x=s("6yg2"),C=s.n(x),k={strtoJson:function(t){var e=new RegExp("=","g"),s=new RegExp("\\&","g");return t='{"'+t+'"}',t=t.replace(e,'":"').replace(s,'","'),-1===t.indexOf("undefined")&&JSON.parse(t)},iscroll:function(t){function e(){var t=0;if(document.documentElement&&document.documentElement.scrollTop?t=document.documentElement.scrollTop:document.body&&(t=document.body.scrollTop),0!==t)return t}function s(){return document.body.clientHeight&&document.documentElement.clientHeight?Math.min(document.body.clientHeight,document.documentElement.clientHeight):Math.max(document.body.clientHeight,document.documentElement.clientHeight)}function a(){return Math.max(document.body.scrollHeight,document.documentElement.scrollHeight)}window.onscroll=function(){e()+s()===a()&&t()}},btnClickLimit:function(t){var e=new Date,s=1e3;void 0!==localStorage.getItem("btnClickTime")&&(s=e.getTime()-Number(localStorage.getItem("btnClickTime"))),s>=500?(t(!0),localStorage.setItem("btnClickTime",e.getTime())):t(!1)},session:function(t,e,s,a){var i=location.href,o="",n=this;if(i=i.split("#")[0].split("?")[1],this.isWeiXin()?o=this.strtoJson(i).code:this.isAlipay()&&(o=this.strtoJson(i).auth_code),void 0===o||localStorage.getItem("code")===o){if(null==n.getCookie("session"))return localStorage.clear(),n.delCookie("session"),localStorage.setItem("flagLocation","true"),location.href=e,!1;var r=location.href;$.get_wechat_jsapi({session:n.getCookie("session"),url:r}).then(function(t){0===t.data.code&&(0===t.data.data.length?n.startSDK("",s):n.startSDK(t.data.data,s))}).catch(function(t){})}else localStorage.setItem("code",o),$.login({code:o}).then(function(t){if(0===t.data.code){var e="",i="";if(""===t.data.data.headimgurl?(e="./../../static/images/user_home/userIcon.png",localStorage.setItem("headimgurl","./../../static/images/user_home/userIcon.png")):(e=t.data.data.headimgurl,localStorage.setItem("headimgurl",t.data.data.headimgurl)),i=t.data.data.nickname,localStorage.setItem("nickname",t.data.data.nickname),n.setCookie("session",t.data.data.session,30),localStorage.setItem("session",t.data.data.session),localStorage.setItem("money",t.data.data.money),localStorage.setItem("installer",t.data.data.installer),localStorage.setItem("unreturn",t.data.data.unreturn),"true"===localStorage.getItem("flagLocation"))return localStorage.setItem("flagLocation",""),n.isWeiXin()&&window.history.back(),n.isAlipay()&&(window.history.back(),window.location.reload()),!1;s({headimgurl:e,nickname:i,installer:t.data.data.installer,money:t.data.data.money,unreturn:t.data.data.unreturn})}else 2===t.data.code&&(a.$store.state.showMask.mask={ishow_mask:!0,ishow_qr:!0,not_click:!0},setTimeout(function(){n.CloseWindow()},1e4))}).catch(function(t){})},isWeiXin:function(){return"micromessenger"==window.navigator.userAgent.toLowerCase().match(/MicroMessenger/i)},isAlipay:function(){return"alipay"==window.navigator.userAgent.toLowerCase().match(/alipay/i)},startSDK:function(t,e){var s=this;s.isWeiXin()&&(wx.config({appId:t.appId,timestamp:t.timestamp,nonceStr:t.nonceStr,signature:t.signature,jsApiList:["onMenuShareTimeline","onMenuShareAppMessage","onMenuShareQQ","onMenuShareWeibo","onMenuShareQZone","startRecord","stopRecord","onVoiceRecor","playVoice","pauseVoice","stopVoice","onVoicePlay","uploadVoice","downloadVoice","chooseImage","previewImage","uploadImage","downloadImage","translateVoice","getNetworkType","openLocation","getLocation","hideOptionMenu","showOptionMenu","hideMenuItems","showMenuItems","hideAllNonBaseMenuItem","showAllNonBaseMenuItem","closeWindow","scanQRCode","chooseWXPay","openProductSpecificView","addCard","chooseCard","openCard"]}),wx.error(function(t){}),wx.ready(function(){e()})),s.isAlipay()&&e()},scan:function(t){var e=this,s="";e.isWeiXin()&&wx.scanQRCode({needResult:1,scanType:["qrCode","barCode"],success:function(e){s=e.resultStr,t(s)},error:function(t){}}),e.isAlipay()&&document.addEventListener("AlipayJSBridgeReady",function(){-1===navigator.userAgent.indexOf("AlipayClient")||(Ali.alipayVersion.slice(0,3)>=8.1?Ali.scan({type:"qr"},function(e){e.errorCode||(e=e.qrCode,t(e))}):Ali.alert({title:"亲",message:"请升级您的钱包到最新版",button:"确定"}))},!1)},pay:function(t,e){this.isWeiXin()&&wx.chooseWXPay({timestamp:t.jsApiParameters.timeStamp,nonceStr:t.jsApiParameters.nonceStr,package:t.jsApiParameters.package,signType:t.jsApiParameters.signType,paySign:t.jsApiParameters.paySign,success:function(t){e()}}),this.isAlipay()&&e(t.jsApiParameters)},locationFn_browser:function(t){this.isWeiXin()?wx.getLocation({type:"wgs84",success:function(e){t(e)},cancel:function(){t()}}):this.isAlipay()&&AlipayJSBridge.call("getCurrentLocation",{bizType:"didi"},function(e){if(e.error)return void t();t(e)})},CloseWindow:function(){this.isWeiXin()&&WeixinJSBridge.invoke("closeWindow",{},function(t){t.err_msg}),this.isAlipay()&&AlipayJSBridge.call("exitApp")},setCookie:function(t,e,s){var a=new Date;a.setTime(a.getTime()+60*s*1e3),document.cookie=t+"="+escape(e)+";expires="+a.toGMTString()},getCookie:function(t){var e=void 0,s=new RegExp("(^| )"+t+"=([^;]*)(;|$)");return(e=document.cookie.match(s))?unescape(e[2]):null},delCookie:function(t){var e=new Date;e.setTime(e.getTime()-6e4),document.cookie=t+"=v; expires="+e.toGMTString()}};g.a.defaults.timeout=5e3,g.a.defaults.headers.post["Content-Type"]="application/x-www-form-urlencoded;charset=UTF-8",g.a.defaults.headers.get["Content-Type"]="application/x-www-form-urlencoded;charset=UTF-8";var y=window.location.href.split("index.php")[0];g.a.defaults.baseURL=y,g.a.interceptors.request.use(function(t){return"post"===t.method&&(t.data=v.a.stringify(t.data)),t},function(t){return _.a.reject(t)});var T={ceshi_jsonp:function(t,e){return P(t,e,null)},login:function(t){return b("index.php?mod=api&act=platform&opt=login","post",t)},order_status:function(t){return b("index.php?mod=api&act=platform&opt=order_status","post",t)},get_stationInfo:function(t){return b("index.php?mod=api&act=platform&opt=get_station_info","post",t)},paydirect:function(t){return b("index.php?mod=api&act=platform&opt=borrow","post",t)},get_appid:function(t){return b("index.php?mod=api&act=platform&opt=get_appid","post",t)},get_wechat_jsapi:function(t){return b("index.php?mod=api&act=platform&opt=get_wechat_jsapi","post",t)}};g.a.interceptors.response.use(function(t){var e="",s="",a=location.href,i=window.location.href.split("index.php")[0],o=a.split("#/")[1];return 5===t.data.code?(k.isWeiXin()?s=0:k.isAlipay()&&(s=1),T.get_appid({platform:s}).then(function(t){k.isWeiXin()?e="https://open.weixin.qq.com/connect/oauth2/authorize?appid="+t.data.data.appId+"&redirect_uri="+encodeURI(i)+"index.php%3fmod%3dwechat%26act%3duser%26opt%3dpay%26router%3d"+o+"&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect":k.isAlipay()&&(e="https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id="+t.data.data.appId+"&scope=auth_user&redirect_uri="+encodeURI(i)+"index.php%3fmod%3dwechat%26act%3duser%26opt%3dpay%26router%3d"+o),localStorage.clear(),k.delCookie("session"),localStorage.setItem("flagLocation","true"),location.href=e}).catch(function(t){})):null==k.getCookie("session")||void 0===k.getCookie("session")||k.setCookie("session",k.getCookie("session"),30),t},function(t){return _.a.reject(t)});var b=function(t,e,s){return g()({url:t,method:e,data:s})},P=function(t,e,s){C()(t,s,e)},$=T,S={data:function(){return{props:""}},computed:{ishow:function(){var t=this;return t.props=t.$store.state.map.toast_config.content,t.$store.state.map.toast_config.flag}},updated:function(){var t=this;setTimeout(function(){t.$store.state.map.toast_config.flag=!1},t.$store.state.map.toast_config.time)}},I=function(){var t=this,e=t.$createElement,s=t._self._c||e;return t.ishow?s("div",{staticClass:"module-mask"},[s("div",{staticClass:"toast"},[t._v(t._s(t.props))])]):t._e()},M=[],U={render:I,staticRenderFns:M},D=U,N=s("/Xao"),A=a,F=N(S,D,!1,A,"data-v-4f485241",null),z=F.exports,R={name:"alert",data:function(){return{text:"",cancleText:"取消",sureText:"确定",btnshow:!0}},methods:{cancle:function(){this.$store.state.showMask.mask.ishow_mask=!1,this.$store.state.showMask.mask.ishow_alert=!1},sure:function(){var t=this,e=null;if(e=t.$store.state.showMask.mask.saveText,t.$store.state.showMask.alert.repeat)return t.$store.state.showMask.alert.repeatCallBack(function(){}),t.$store.state.showMask.mask.ishow_alert=!1,!1;if(t.$store.state.showMask.mask.ishow_mask=!1,t.$store.state.showMask.mask.ishow_alert=!1,t.$store.state.showMask.mask.saveText){var s=function(s,a){e[s]?t[s]=e[s]:t[s]=a};t.text=e.content,t.$store.state.showMask.alert.btnNum=e.btnNum,s("cancleText","取消"),s("sureText","确定")}t.$store.state.showMask.mask.ishow_mask=!1}},mounted:function(){this.text=this.$store.state.showMask.alert.content,this.$store.state.showMask.alert.cancleText&&(this.cancleText=this.$store.state.showMask.alert.cancleText),this.$store.state.showMask.alert.sureText&&(this.sureText=this.$store.state.showMask.alert.sureText)},updated:function(){var t=this;t.btnshow=2===t.$store.state.showMask.alert.btnNum}},E=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"alert layout col"},[s("div",{staticClass:"box col all-center"},[t._v(t._s(t.text))]),t._v(" "),s("div",{staticClass:"btns layout row one-halfPx-Top"},[t.btnshow?s("div",{staticClass:"cancle row all-center one-halfPx-Right",on:{click:function(e){e.stopPropagation(),t.cancle(e)}}},[t._v(t._s(t.cancleText))]):t._e(),t._v(" "),s("div",{staticClass:"sure row all-center",on:{click:function(e){e.stopPropagation(),t.sure(e)}}},[t._v(t._s(t.sureText))])])])},W=[],J={render:E,staticRenderFns:W},L=J,q=s("/Xao"),X=i,K=q(R,L,!1,X,null,null),H=K.exports,j={name:"web-component-loading",data:function(){return{}}},V=function(){var t=this,e=t.$createElement;t._self._c;return t._m(0,!1,!1)},O=[function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"web-component-loading"},[s("div",{staticClass:"loading layout all-center"},[s("img",{attrs:{src:"/static/images/svg/Spinner.svg"}})])])}],B={render:V,staticRenderFns:O},G=B,Q=s("/Xao"),Z=o,Y=Q(j,G,!1,Z,null,null),tt=Y.exports,et={name:"web-component-loading",data:function(){return{}},computed:{show_loading:function(){return this.$store.state.showMask.loader.show_loading},show_loaderTip:function(){return this.$store.state.showMask.loader.show_loaderTip}},components:{loading:tt}},st=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"web-component-loading"},[s("div",{staticClass:"loader"},[s("img",{attrs:{src:"/static/images/loading.png"}}),t._v(" "),t.show_loaderTip?s("div",{staticClass:"tip"},[s("div",{staticClass:"text"},[t._v("感谢使用街借伞！")]),t._v(" "),s("div",[t._v("点击左上角关闭页面")])]):t._e()]),t._v(" "),t.show_loading?s("loading"):t._e()],1)},at=[],it={render:st,staticRenderFns:at},ot=it,nt=s("/Xao"),rt=n,ct=nt(et,ot,!1,rt,null,null),lt=ct.exports,ht={name:"LoaderCircle",data:function(){return{ishow:!0}},computed:{}},dt=function(){var t=this,e=t.$createElement,s=t._self._c||e;return t.ishow?s("div",{staticClass:"LoaderCircle"},[s("div",{staticClass:"loader"})]):t._e()},ut=[],mt={render:dt,staticRenderFns:ut},pt=mt,_t=s("/Xao"),wt=r,gt=_t(ht,pt,!1,wt,null,null),ft=gt.exports,vt={name:"mask",data:function(){return{text:"<div>asdasdassad</div>"}},components:{oAlert:H,loader:lt,loaderCircle:ft},methods:{hide:function(){if(0==this.$store.state.showMask.mask.not_click)return!1;this.$store.state.showMask.mask.ishow_mask=!1,this.$store.state.showMask.mask.ishow_alert&&(this.$store.state.showMask.mask.ishow_alert=!1)}},computed:{ishow_mask:function(){var t=this;this.$store.state.showMask.alert.repeat;return t.$store.state.showMask.mask.ishow_mask},ishow_Html:function(){return this.$store.state.showMask.mask.ishow_Html},ishow_alert:function(){this.$store.state.showMask.alert.repeat;return this.$store.state.showMask.mask.ishow_alert},ishow_loader:function(){return this.$store.state.showMask.mask.ishow_loader},ishow_loaderCircle:function(){return this.$store.state.showMask.mask.ishow_loaderCircle},ishow_qr:function(){return this.$store.state.showMask.mask.ishow_qr}}},xt=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{directives:[{name:"show",rawName:"v-show",value:t.ishow_mask,expression:"ishow_mask"}],staticClass:"mask",on:{click:function(e){e.stopPropagation(),t.hide(e)}}},[t.ishow_alert?s("oAlert"):t._e(),t._v(" "),t.ishow_loader?s("loader"):t._e(),t._v(" "),t.ishow_loaderCircle?s("loaderCircle"):t._e(),t._v(" "),t.ishow_Html?s("div",{attrs:{id:"htmlText"},domProps:{innerHTML:t._s(t.text)}}):t._e(),t._v(" "),t.ishow_qr?s("div",{staticClass:"qr"},[s("div",{staticClass:"qrTitle"},[t._v("您未关注公众号")]),t._v(" "),s("img",{attrs:{src:"/static/images/JJsanWechat.jpg",alt:""}}),t._v(" "),s("div",{staticClass:"qrText"},[t._v("长按识别二维码关注公众号")])]):t._e()],1)},Ct=[],kt={render:xt,staticRenderFns:Ct},yt=kt,Tt=s("/Xao"),bt=c,Pt=Tt(vt,yt,!1,bt,null,null),$t=Pt.exports,St={name:"app",data:function(){return{ishowContent:!1}},watch:{$route:function(t,e){var s=this,a="oneKeyUse"==t.name&&"afterPay"==e.name&&!1===s.$store.state.html.router,i="afterPay"==t.name&&"oneKeyUse"==e.name&&!0===s.$store.state.html.router;(a||i)&&(s.ishowContent=!1,k.CloseWindow())}},mounted:function(){var t=this,e=location.href,s=window.location.href.split("index.php")[0];t.$store.state.html.url=s,t.ishowContent=!1;var a=e.split("#/")[1],i=e.split("?")[1].split("#")[0],o=k.strtoJson(i).flag;if("true"===localStorage.getItem("tozhima")&&"oneKeyUse"===a&&1!=o)return localStorage.setItem("tozhima","false"),k.CloseWindow();t.session(function(){t.ishowContent=!0,t.$store.state.showMask.mask={ishow_mask:!1,ishow_loaderCircle:!1,not_click:!0}})},components:{oMask:$t,toast:z},methods:{session:function(t){function e(e){var a="",i=window.location.href.split("index.php")[0];k.isWeiXin()?a="https://open.weixin.qq.com/connect/oauth2/authorize?appid="+e+"&redirect_uri="+encodeURI(i)+"index.php%3fmod%3dwechat%26act%3duser%26opt%3dpay%26router%3d"+o+"&response_type=code&scope=snsapi_base&state=STATE#wechat_redirect":k.isAlipay()&&(a="https://openauth.alipay.com/oauth2/publicAppAuthorize.htm?app_id="+e+"&scope=auth_user&redirect_uri="+encodeURI(i)+"index.php%3fmod%3dwechat%26act%3duser%26opt%3dpay%26router%3d"+o),s.$store.state.html.noDoConfig?k.session(e,a,function(e){s.$store.state.html.noDoConfig=!1,t()},s):(s.$store.state.html.noDoConfig=!0,t())}var s=this,a=0,i=location.href,o=i.split("#/")[1];s.$store.state.showMask.mask={ishow_mask:!0,ishow_loaderCircle:!0,not_click:!1},k.isWeiXin()?a=0:k.isAlipay()&&(a=1),null!=k.getCookie("appId")?e(k.getCookie("appId")):$.get_appid({platform:a}).then(function(t){0===t.data.code&&(k.setCookie("appId",t.data.data.appId,10080),e(t.data.data.appId))}).catch(function(t){})}}},It=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{attrs:{id:"app"}},[t.ishowContent?s("div",{attrs:{id:"routerView"}},[s("router-view")],1):t._e(),t._v(" "),s("oMask"),t._v(" "),s("toast")],1)},Mt=[],Ut={render:It,staticRenderFns:Mt},Dt=Ut,Nt=s("/Xao"),At=l,Ft=Nt(St,Dt,!1,At,null,null),zt=Ft.exports,Rt=s("zO6J"),Et=function(t){document.title=t},Wt={name:"userFlow",data:function(){return{imgUrl_001:"/static/images/useFlow/1.0@2x.png",imgUrl_002:"/static/images/useFlow/next1@2x.png",imgUrl_003:"/static/images/useFlow/next3@2x.png",imgUrl_004:"/static/images/useFlow/3.1@2x.png",imgUrl_005:"/static/images/useFlow/2.0@2x.png",imgUrl_006:"/static/images/useFlow/next1@2x.png",imgUrl_007:"/static/images/useFlow/4.0@2x.png",imgUrl_008:"/static/images/useFlow/5.0@2x.png",imgUrl_009:"/static/images/useFlow/next1@2x.png",imgUrl_010:"/static/images/useFlow/next3@2x.png",imgUrl_011:"/static/images/useFlow/6.0@2x.png",imgUrl_012:"/static/images/useFlow/7.0@2x.png"}},methods:{},mounted:function(){Et("使用帮助")}},Jt=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"userFlow"},[s("div",{staticClass:"view layout col"},[t._m(0,!1,!1),t._v(" "),s("div",{staticClass:"col box"},[s("div",{staticClass:"content layout col"},[s("div",{staticClass:"col"},[s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_001,expression:"imgUrl_001"}],staticClass:"img1"})]),t._v(" "),s("div",{staticClass:"row lrb-center"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_002,expression:"imgUrl_002"}],staticClass:"img2"})]),t._v(" "),s("div",{staticClass:"row"})]),t._v(" "),s("div",{staticClass:"col"},[s("div",{staticClass:"row"}),t._v(" "),s("div",{staticClass:"row lrb-center"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_003,expression:"imgUrl_003"}],staticClass:"img2"})]),t._v(" "),s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_004,expression:"imgUrl_004"}],staticClass:"img1"})])]),t._v(" "),s("div",{staticClass:"col"},[s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_005,expression:"imgUrl_005"}],staticClass:"img1"})]),t._v(" "),s("div",{staticClass:"row lrb-center"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_006,expression:"imgUrl_006"}],staticClass:"img2"})]),t._v(" "),s("div",{staticClass:"row"})]),t._v(" "),s("div",{staticClass:"col"},[s("div",{staticClass:"row"}),t._v(" "),s("div",{staticClass:"row lrb-center"}),t._v(" "),s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_007,expression:"imgUrl_007"}],staticClass:"img1"})])])])])]),t._v(" "),s("div",{staticClass:"view layout col"},[t._m(1,!1,!1),t._v(" "),s("div",{staticClass:"col box"},[s("div",{staticClass:"content layout col"},[s("div",{staticClass:"col"},[s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_008,expression:"imgUrl_008"}],staticClass:"img1"})]),t._v(" "),s("div",{staticClass:"row lrb-center"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_009,expression:"imgUrl_009"}],staticClass:"img2"})]),t._v(" "),s("div",{staticClass:"row"})]),t._v(" "),s("div",{staticClass:"col"},[s("div",{staticClass:"row"}),t._v(" "),s("div",{staticClass:"row lrb-center"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_010,expression:"imgUrl_010"}],staticClass:"img2"})]),t._v(" "),s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_011,expression:"imgUrl_011"}],staticClass:"img1"})])]),t._v(" "),s("div",{staticClass:"col"},[s("div",{staticClass:"row"},[s("img",{directives:[{name:"lazy",rawName:"v-lazy",value:t.imgUrl_012,expression:"imgUrl_012"}],staticClass:"img1"})]),t._v(" "),s("div",{staticClass:"row lrb-center"}),t._v(" "),s("div",{staticClass:"row"})]),t._v(" "),t._m(2,!1,!1)])])])])},Lt=[function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"title"},[s("span",[t._v("借伞")])])},function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"title"},[s("span",[t._v("还伞")])])},function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"col"},[s("div",{staticClass:"row"}),t._v(" "),s("div",{staticClass:"row lrb-center"}),t._v(" "),s("div",{staticClass:"row"})])}],qt={render:Jt,staticRenderFns:Lt},Xt=qt,Kt=s("/Xao"),Ht=h,jt=Kt(Wt,Xt,!1,Ht,null,null),Vt=jt.exports,Ot={name:"afterPay",data:function(){return{lists:[],phone:"tel:4009008113",phoneText:"400-900-8113",fixedText:{text:"如果您需要其他帮助，欢迎致电客服",ishowPhone:!0},scanData:{},btnNum:0,ishowContent:!1,orderid:""}},components:{userFlow:Vt},mounted:function(){"true"===localStorage.getItem("ifclose")?(localStorage.setItem("ifclose","false"),k.CloseWindow()):this.ishowContent=!0;var t=this,e=location.href;if(e=e.split("#")[0].split("?")[1],this.orderid=k.strtoJson(e).orderid,this.orderid)t.scanData.fee_strategy=localStorage.getItem("fee_str");else{if(this.scanData=this.$route.params.scanData,void 0===this.$route.params.orderid){this.$store.state.html.url}this.orderid=this.$route.params.orderid}t.show_sending()},methods:{btnTap:function(t){var e=this;switch(t){case 2:e.$store.state.html.oneKeyUseRouter=!0,e.$store.state.html.router=!0,e.$router.replace("oneKeyUse");break;case 3:e.ishow=!0,e.ishowContent=!1;break;case 4:var s=e.$store.state.html.url;localStorage.setItem("ifclose","true"),window.location.href=s+"index.php?mod=wechat&act=user&opt=center#/userHome";break;case 5:e.ishowContent=!1,e.$store.state.html.router=!0,e.$router.replace("oneKeyUse");break;case 6:var a=e.$store.state.html.url;localStorage.setItem("ifclose","true"),window.location.href=a+"index.php?mod=wechat&act=user&opt=map#/map"}},show_notOnline:function(){var t=this;this.imgPath="/static/images/one_key_use/notonline@2x.png",this.solution="请扫描其他伞柜",this.stateText="伞柜不在线",this.btnText="扫码其他伞柜",this.lists=[{text:"伞柜发生断网断电故障，给您带来的便深感抱歉",ishowPhone:!1},t.fixedText]},show_occupation:function(){var t=this;this.imgPath="/static/images/one_key_use/occupation@2x.png",this.stateText="请稍候",this.solution="伞柜正被其他用户使用",this.btnText="点击重试",this.btnNum=2,this.lists=[{text:"伞柜同一时间内仅支持一位用户借伞，其他用户操作完成后您才可以进行借伞操作给您带来的不便深感抱歉",ishowPhone:!1},t.fixedText]},show_noHasUse:function(){var t=this;this.imgPath="/static/images/one_key_use/san@2x.png",this.stateText="当前伞柜",this.solution="没有可用的伞",this.btnText="扫码其他伞柜",this.lists=[{text:"伞柜中仅存故障伞或伞已全部借出，给您带来的便深感抱歉",ishowPhone:!1},t.fixedText]},show_recharge:function(){var t=this;this.imgPath="/static/images/one_key_use/recharge@2x.png",this.stateText="账户余额18元",this.solution="需预存12元才能借伞",this.btnText="立即充值",this.lists=[{text:"借伞时，需支付30元押金，当钱包余额大于等于30元时，自动把30元余额转为押金",ishowPhone:!1},{text:"还伞后，从押金中扣除用伞费用（1元/12小时），并自动将剩余押金转为余额",ishowPhone:!1},t.fixedText]},show_sending:function(){Et("系统确认中");var t=this;this.imgPath="/static/images/one_key_use/waiting.gif",this.stateText="请稍候",this.solution="系统确认中",this.btnText="",this.lists=[{text:"当伞槽绿灯闪烁时，取出JJ伞",ishowPhone:!1},{text:"如借伞失败，押金将自动转为余额，可直接提现",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},t.fixedText];var e=0;localStorage.setItem("tozhima","false");var s=setInterval(function(){$.order_status({session:localStorage.getItem("session"),order_id:t.orderid}).then(function(a){e+=1,0==a.data.data.status?(alert("订单未支付成功！"),k.CloseWindow()):1==a.data.data.status?(Et("正在出伞"),t.solution="正在出伞"):3==a.data.data.status?t.show_hasSend(a.data.data):4==a.data.data.status?(e=0,clearInterval(s),t.show_takeFail()):2==a.data.data.status?(clearInterval(s),t.show_takeSuccess()):6==a.data.data.status?(clearInterval(s),t.show_occupation()):5==a.data.data.status?(clearInterval(s),t.show_noResponse()):7==a.data.data.status&&(clearInterval(s),k.CloseWindow())}).catch(function(t){})},1e3)},show_hasSend:function(t,e){var s=this;this.imgPath="/static/images/one_key_use/waiting.gif",this.stateText="请取伞",this.solution=t.slot+"号槽伞已出",this.btnText="",this.lists=[{text:"当伞槽绿灯闪烁时，取出JJ伞",ishowPhone:!1},{text:"如借伞失败，押金将自动转为余额，可直接提现",ishowPhone:!1},{text:"借伞收费"+s.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},s.fixedText]},show_takeSuccess:function(){Et("街借伞");var t=this;this.imgPath="/static/images/one_key_use/success@2x.png",this.stateText="感谢您的使用",this.solution="取伞成功",this.btnText="了解还伞流程",this.btnNum=3,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},t.fixedText]},show_takeFail:function(){Et("街借伞");var t=this;this.imgPath="/static/images/one_key_use/fail@2x.png",this.stateText="请在有效的时间内取伞",this.solution="取伞失败",this.btnText="返回用户中心",this.btnText2="继续借伞",this.btnNum=4,this.btnNum2=5,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},t.fixedText]},show_noResponse:function(){Et("出伞失败");var t=this;this.imgPath="/static/images/one_key_use/fail@2x.png",this.stateText="设备无响应",this.solution="出伞失败",this.btnText="返回用户中心",this.btnText2="附近网点",this.btnNum=4,this.btnNum2=6,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},t.fixedText]}}},Bt=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"oneKeyUse"},[t.ishowContent?s("div",{staticClass:"content layout col"},[s("div",{staticClass:"header"},[s("div",{staticClass:"box layout col tb-center"},[s("img",{staticClass:"tip-icon",attrs:{src:t.imgPath}}),t._v(" "),s("div",{staticClass:"state"},[t._v(t._s(t.stateText))]),t._v(" "),s("div",{staticClass:"solution"},[t._v(t._s(t.solution))])]),t._v(" "),s("div",{staticClass:"btns layout lr-center"},[t.btnText2?s("div",{staticClass:"btn marginRight"},[s("div",{staticClass:"btn layout all-center",on:{click:function(e){t.btnTap(t.btnNum2)}}},[t._v(t._s(t.btnText2))])]):t._e(),t._v(" "),t.btnText?s("div",{staticClass:"btn"},[s("div",{staticClass:"btn layout all-center",on:{click:function(e){t.btnTap(t.btnNum)}}},[t._v(t._s(t.btnText))])]):t._e()])]),t._v(" "),s("div",{staticClass:"footer col"},[s("div",{staticClass:"tips"},[t._m(0,!1,!1),t._v(" "),s("ol",t._l(t.lists,function(e,a){return s("li",{key:a},[t._v("\n            "+t._s(e.text)+"\n            "),e.ishowPhone?s("a",{attrs:{href:t.phone}},[t._v(t._s(t.phoneText))]):t._e()])}))])])]):t._e(),t._v(" "),t.ishow?s("userFlow"):t._e()],1)},Gt=[function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"title"},[s("div",{staticClass:"titleLine"}),s("span",[t._v("温馨提示")])])}],Qt={render:Bt,staticRenderFns:Gt},Zt=Qt,Yt=s("/Xao"),te=d,ee=Yt(Ot,Zt,!1,te,null,null),se=ee.exports,ae={name:"oneKeyUse",data:function(){return{lists:[],ishowContent:!1,phone:"tel:4009008113",phoneText:"400-900-8113",payType:"",fixedText:{text:"如果您需要其他帮助，欢迎致电客服",ishowPhone:!0},btnNum:0,scanData:"",order_id:"",qrcode:""}},mounted:function(){Et("街借伞");var t=location.href;t=t.split("?")[1].split("#")[0];var e=(k.strtoJson(t).code,k.strtoJson(t).flag),s=k.strtoJson(t).qrcode,a=this;!1===a.$store.state.html.oneKeyUseRouter?(a.$store.state.showMask.loader.show_loaderTip=!1,a.$store.state.showMask.loader.show_loading=!0,1==e?(localStorage.setItem("tozhima","false"),a.showContent(s)):(a.$store.state.showMask.mask={ishow_mask:!0,ishow_loader:!0,not_click:!1},a.rescanFn(function(){}))):a.showContent(localStorage.getItem("wechatqrcode"))},methods:{rescanFn:function(t){var e=this;setTimeout(function(){e.$store.state.showMask.loader.show_loading=!1,e.$store.state.showMask.loader.show_loaderTip=!0},600),k.scan(function(s){e.showContent(s),t()})},showContent:function(t){var e=this;if(localStorage.setItem("wechatqrcode",t),!t)return!1;var s=this;s.qrcode=t,$.get_stationInfo({session:k.getCookie("session"),qrcode:t}).then(function(t){if(s.$store.state.showMask.mask={ishow_mask:!1,ishow_loader:!1},localStorage.setItem("fee_str",t.data.data.fee_strategy),t.data.data.url)return localStorage.setItem("tozhima","true"),e.ishowContent=!1,location.href=t.data.data.url,!1;switch(s.scanData=t.data.data,t.data.code){case 0:s.show_recharge();break;case 2:s.show_notOnline();break;case 4:s.show_occupation();break;case 3:s.show_noHasUse();break;case 5.1:s.show_sending();break;case 6:s.show_hasSend();break;case 7:s.show_takeSuccess();break;case 8:s.show_takeFail();break;case 9:s.show_noResponse();break;case 555:return alert("二维码错误！"),k.CloseWindow(),!1}e.ishowContent=!0}).catch(function(t){})},btnTap:function(t){var e=this;k.btnClickLimit(function(s){if(!s)return!1;var a=function(){$.paydirect({session:localStorage.getItem("session"),itemid:e.scanData.tid,stationid:e.scanData.sid,isZhima:e.scanData.isZhima}).then(function(t){0==t.data.code?(e.order_id=t.data.data.orderid,0==t.data.data.paytype?k.pay(t.data.data,function(s){s?document.write(s):e.$router.push({name:"afterPay",params:{scanData:e.scanData,orderid:t.data.data.orderid}})}):(e.$store.state.html.router=!1,e.ishowContent=!1,e.$router.push({name:"afterPay",params:{scanData:e.scanData,orderid:t.data.data.orderid,qrcode:e.qrcode}}))):2==t.data.code&&(e.$store.state.showMask.mask={ishow_mask:!0,ishow_alert:!0},e.$store.state.showMask.alert={content:"设备仍在使用中，请稍后再试!",sureText:"OK",btnNum:1})}).catch(function(t){})},i=0,o=(new Date).getTime();if("undefined"!=i&&o-i<500)return!1;if(i=o,1===t)e.rescanFn(function(){});else if(2===t)e.showContent(),a();else{if(3===t)return e.ishow=!0,e.ishowContent=!1,!1;0===t&&a()}void 0===e.scanData.isZhima&&(e.scanData.isZhima="")})},show_recharge:function(){var t=this;this.imgPath="/static/images/one_key_use/recharge@2x.png",this.stateText="账户余额"+t.scanData.usable_money+"元",0===t.scanData.need_pay?t.solution="钱包余额充足":t.solution="需预存"+t.scanData.need_pay+"元才能借伞",0===t.scanData.need_pay?t.btnText="立即借伞":(t.btnText="立即充值",Et("充值")),this.btnNum=0,this.lists=[{text:"借伞时，需支付"+t.scanData.deposit_need+"元押金，当钱包余额大于等于"+t.scanData.deposit_need+"元时，自动把"+t.scanData.deposit_need+"元余额转为押金",ishowPhone:!1},{text:"还伞后，从押金中扣除用伞费用（"+t.scanData.fee_strategy+"），并自动将剩余押金转为余额",ishowPhone:!1},t.fixedText]},show_notOnline:function(){var t=this;this.imgPath="/static/images/one_key_use/notonline@2x.png",this.solution="请扫描其他伞柜",this.stateText="伞柜不在线",this.btnText="扫码其他伞柜",this.btnNum=1,this.lists=[{text:"伞柜发生断网断电故障，给您带来的不便深感抱歉",ishowPhone:!1},t.fixedText]},show_occupation:function(){var t=this;this.imgPath="/static/images/one_key_use/occupation@2x.png",this.stateText="请稍候",this.solution="伞柜正被其他用户使用",this.btnText="点击重试",this.btnNum=2,this.lists=[{text:"伞柜同一时间内仅支持一位用户借伞，其他用户操作完成后您才可以进行借伞操作给您带来的不便深感抱歉",ishowPhone:!1},t.fixedText]},show_noHasUse:function(){var t=this;this.imgPath="/static/images/one_key_use/san@2x.png",this.stateText="当前伞柜",this.solution="没有可用的伞",this.btnText="扫码其他伞柜",this.btnNum=1,this.lists=[{text:"伞柜中仅存故障伞或伞已全部借出，给您带来的便深感抱歉",ishowPhone:!1},t.fixedText]},show_sending:function(){var t=this;this.imgPath="/static/images/one_key_use/waiting.gif",this.stateText="请稍候",this.solution="正在出伞",this.btnText="",this.lists=[{text:"当伞槽绿灯闪烁时，取出JJ伞",ishowPhone:!1},{text:"如借伞失败，押金将自动转为余额，可直接提现",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},t.fixedText];var e=0,s=setInterval(function(){$.order_status({session:localStorage.getItem("session"),order_id:t.order_id}).then(function(a){e+=1,1==a.data.data.status||(5==a.data.data.status?t.show_hasSend(a.data.data):99==a.data.data.status?(e=0,clearInterval(s),t.show_takeFail()):2==a.data.data.status?(clearInterval(s),t.show_takeSuccess()):65==a.data.data.status?(clearInterval(s),t.show_occupation()):(clearInterval(s),t.show_noResponse()))}).catch(function(t){})},1e3)},show_hasSend:function(t,e){var s=this;this.imgPath="/static/images/one_key_use/waiting.gif",this.stateText="请取伞",this.solution=t.slot+"号槽伞已出",this.btnText="",this.lists=[{text:"当伞槽绿灯闪烁时，取出JJ伞",ishowPhone:!1},{text:"如借伞失败，押金将自动转为余额，可直接提现",ishowPhone:!1},{text:"借伞收费"+s.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},s.fixedText]},show_takeSuccess:function(){var t=this;this.imgPath="/static/images/one_key_use/success@2x.png",this.stateText="感谢您的使用",this.solution="取伞成功",this.btnText="了解还伞流程",this.btnNum=3,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},{text:"若15天内未还伞，系统将自动判定为伞已遗失，会从押金中扣减费用"+t.scanData.deposit_need+"元",ishowPhone:!1},t.fixedText]},show_takeFail:function(){var t=this;this.imgPath="/static/images/one_key_use/fail@2x.png",this.stateText="请在有效的时间内取伞",this.solution="取伞失败",this.btnText="了解还伞流程",this.btnNum=3,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},{text:"若15天内未还伞，系统将自动判定为伞已遗失，会从押金中扣减费用"+t.scanData.deposit_need+"元",ishowPhone:!1},t.fixedText]},show_noResponse:function(){var t=this;this.imgPath="/static/images/one_key_use/fail@2x.png",this.stateText="设备无响应",this.solution="出伞失败",this.btnText="了解还伞流程",this.btnNum=3,this.lists=[{text:"请检查伞是否可用，如发现伞已损坏，请在两分钟内将伞归还，不产生任何费用",ishowPhone:!1},{text:"借伞收费"+t.scanData.fee_strategy+"，费用会从押金中扣减",ishowPhone:!1},{text:"若15天内未还伞，系统将自动判定为伞已遗失，会从押金中扣减费用"+t.scanData.deposit_need+"元",ishowPhone:!1},t.fixedText]}}},ie=function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"oneKeyUse"},[t.ishowContent?s("div",{staticClass:"content layout col"},[s("div",{staticClass:"header"},[s("div",{staticClass:"box layout col tb-center"},[s("img",{staticClass:"tip-icon",attrs:{src:t.imgPath}}),t._v(" "),s("div",{staticClass:"state"},[t._v(t._s(t.stateText))]),t._v(" "),s("div",{staticClass:"solution"},[t._v(t._s(t.solution))])]),t._v(" "),s("div",{staticClass:"btns layout lr-center"},[t.btnText?s("div",{staticClass:"btn"},[s("div",{staticClass:"btn layout all-center",on:{click:function(e){t.btnTap(t.btnNum)}}},[t._v(t._s(t.btnText))])]):t._e()])]),t._v(" "),s("div",{staticClass:"footer col"},[s("div",{staticClass:"tips"},[t._m(0,!1,!1),t._v(" "),s("ol",t._l(t.lists,function(e,a){return s("li",{key:a},[t._v("\n            "+t._s(e.text)+"\n            "),e.ishowPhone?s("a",{attrs:{href:t.phone}},[t._v(t._s(t.phoneText))]):t._e()])}))])])]):t._e()])},oe=[function(){var t=this,e=t.$createElement,s=t._self._c||e;return s("div",{staticClass:"title"},[s("div",{staticClass:"titleLine"}),s("span",[t._v("温馨提示")])])}],ne={render:ie,staticRenderFns:oe},re=ne,ce=s("/Xao"),le=u,he=ce(ae,re,!1,le,null,null),de=he.exports,ue=location.href,me=ue.split("?")[1],pe=k.strtoJson(me).orderid;pe&&(location.href=ue.split("?")[0]+"?mod=wechat&act=user&opt=pay&orderid="+pe+"#/afterPay");var _e=k.strtoJson(me).router,we={path:"/",redirect:"/oneKeyUse"};null!=_e&&(we.redirect="/"+_e),m.a.use(Rt.a);var ge=new Rt.a({routes:[{path:"/oneKeyUse",name:"oneKeyUse",component:de},{path:"/useFlow",name:"useFlow",component:Vt},{path:"/afterPay",name:"afterPay",component:se},we]}),fe=ge,ve=s("9rMa"),xe={session:"",platform:0,appid:"",debug:{debugShow:!0,debugTextList:[]},router:!1,oneKeyUseRouter:!1,scrollTop:0,noDoConfig:!0,url:""},Ce={state:xe},ke={scan_result:""},ye={state:ke},Te={mask:{ishow_mask:!1,ishow_alert:!1,ishow_loader:!1,ishow_loaderCircle:!1,ishow_Html:!1,ishow_qr:!1,htmlText:"",saveText:null,not_click:!0},alert:{content:"",cancleText:"",sureText:"",btnNum:null,repeat:null,repeatCallBack:null},loader:{show_loading:!1,show_loaderTip:!1}},be={state:Te},Pe={reload:0,stations:"",mapStations:"",stationsLength:"",stationsKey:"",stationsLengthKey:"",inputValue:"",inputKeyWord:"",init_point:"",swiperFalt:!1,ifDistance:!0,ifList:!1,toast_config:{content:"",time:2e3,flag:!1},loader_config:{flag:!1}},$e={state:Pe};m.a.use(ve.a);var Se=new ve.a.Store({modules:{html:Ce,scan:ye,showMask:be,map:$e}}),Ie=s("201h"),Me=s.n(Ie);m.a.config.productionTip=!1,m.a.use(Me.a,{preLoad:1,error:"./static/images/svg/error_img.svg",loading:"./static/images/svg/loading_img.svg",attempt:5}),new m.a({el:"#app",router:fe,store:Se,template:"<App/>",components:{App:zt}})},"P/iQ":function(t,e){},eGlH:function(t,e){},gG2Y:function(t,e){},hqUG:function(t,e){},lbjU:function(t,e){},pN4k:function(t,e){}},["NHnr"]);
//# sourceMappingURL=app.e33c6089c6c1c4182f98.js.map