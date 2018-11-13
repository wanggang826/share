var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: '',
    store: '',
    phone: '',
    lat: '',
    lnt: '',
    count: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {

    var page = this;

    page.fromNet = 0;
    getUser(page);

    wx.getStorage({
      key: 'store',
      success: function (res) {
        console.log('getStore', res);
        page.setData({
          lat: res.data.latitude,
          lnt: res.data.longitude,
        })
        var id = res.data.index;

        var times = 0;

        var timeOut = setTimeout(function fn() {
          if (page.data.user) {
            wx.request({
              url: URL,
              data: {
                mod: 'api',
                act: 'weapp',
                opt: 'detail',
                session: page.data.user.session,
                shop_station_id: id
              },
              success: function (res) {
                console.log('商铺详情数据', res);
                if (res.data.code === 0) {
                  holdUser(page);

                  var phone = res.data.data.shop_info.phone || '';
                  var store = res.data.data;
                  if (!store.shop_info.carousel.length) {
                    store.shop_info.carousel.push('../../assets/img/default.jpg');
                  }
                  console.log('整理后的商铺详情数据', store);
                  page.setData({
                    store: store,
                    phone: phone
                  })
                } else {
                  if (res.data.code == 5) {
                    if (!page.working) {
                      page.fromNet = 1;
                      getUser(page);
                    }
                  }
                  if (times < 100) {
                    timeOut = setTimeout(fn, 50);
                  }
                }
              },
              fail: function () {
                if (times < 100) {
                  timeOut = setTimeout(fn, 50);
                }
              },
              complete: function () {
                times++;
              }
            })
          } else {
            if (times < 100) {
              timeOut = setTimeout(fn, 50);
            }
          }
        }, 50);
      },
    })
  },

  onShow: function () {
    var page = this;
    if (Date.now() - page.data.user.time > 1500000 || (!page.data.user && !page.working)) {
      page.fromNet = 1;
      getUser(page);
    }
  },

  //按钮点击借伞
  borrow: function () {
    var page = this;
    var session = page.data.user.session;
    wx.scanCode({
      onlyFromCamera: true,
      success: (res) => {
        console.log('二维码', res);
        var times = 0;
        var timeOut = setTimeout(function fn() {
          if (page.data.user) {
            wx.request({
              url: URL,
              data: {
                mod: 'api',
                act: 'weapp',
                opt: 'borrow',
                qrcode: res.result,
                session: page.data.user.session,
              },
              success: function (res) {
                console.log('请求成功:', res);
                var code = res.data.code;
                if (code === 555) {
                  wx.showToast({
                    title: '二维码错误',
                    duration: 2000
                  });
                } else if (code == 5) {
                  if (!page.working) {
                    page.fromNet = 1;
                    getUser(page);
                  }
                  if (times < 100) {
                    timeOut = setTimeout(fn, 50);
                  }
                } else {
                  var wallet;
                  if (code === 0) {
                    holdUser(page);
                    wallet = res.data.data;
                  } else {
                    wallet = {};
                  }
                  wallet.code = res.data.code;
                  console.log('存取的wallet', wallet);
                  wx.setStorage({
                    key: 'wallet',
                    data: wallet,
                    success: function () {
                      wx.navigateTo({
                        url: '../pay/pay',
                      })
                    }
                  });
                }
              },
              fail: function () {
                if (times < 100) {
                  timeOut = setTimeout(fn, 50);
                }
              },
              complete: function () {
                times++;
              }
            });
          } else {
            if (!page.working) {
              page.fromNet = 1;
              getUser(page);
            }
            if(times<100){
              timeOut = setTimeout(fn, 50);
            }
          }
        }, 50)
      }
    });
  },

  //联系我点击事件
  call: function () {
    var page = this;
    wx.makePhoneCall({
      phoneNumber: page.data.phone,
      fail: function () {
        wx.showModal({
          title: '商铺电话',
          content: page.data.phone,
          showCancel: 'false'
        })
      }
    })
  },

  //到这去点击事件
  goThere: function () {
    var page = this;
    console.log('商铺详细信息', page.data.store)
    wx.openLocation({
      latitude: page.data.lat,
      longitude: page.data.lnt,
      name: page.data.store.shop_info.name,
      address: page.data.store.shop_info.address
    })
  }
})