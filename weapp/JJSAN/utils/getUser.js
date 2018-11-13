var URL = require('url.js');

var getUser = function (page) {

  page.working = 1;

  if (page.fromNet) {
    var times = 0;
    var getUser = setTimeout(function fn() {
      wx.login({
        success: function (res) {
          //登录服务器
          var code = res.code;
          console.log('登录数据', res);
          wx.getUserInfo({
            success: function (res) {
              console.log('userInfo', res);
              var user = {};
              user.name = res.userInfo.nickName;
              user.img = res.userInfo.avatarUrl;

              wx.request({
                url: URL,
                data: {
                  mod: 'api',
                  act: 'weapp',
                  opt: 'login',
                  code: code,
                  encryptedData: res.encryptedData,
                  iv: res.iv
                },
                success: function (res) {
                  if (res.data.code === 0) {
                    console.log('手动从网络获取session', res);
                    clearInterval(getUser);
                    user.session = res.data.data.session;
                    user.time = Date.now();
                    page.setData({
                      user: user
                    });
                    wx.setStorage({
                      key: 'user',
                      data: user,
                    });
                    page.working = 0;
                  }
                },
                fail: function () {
                  if (times < 100) {
                    getUser = setTimeout(fn, 50);
                  }
                },
                complete: function () {
                  times++;
                }
              });
            },
            fail: function () {
              // wx.showModal({
              //   showCancel: false,
              //   title: '温馨提示',
              //   content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。'
              // });
            }
          })
        },
        fail: function () {
          getUser = setTimeout(fn, 50);
        }
      });
    }, 50);
  } else {
    wx.getStorage({
      key: 'user',
      success: function (res) {
        console.log(((Date.now() - res.data.time) / (1000 * 60)).toFixed(2) + '分钟');
        if (Date.now() - res.data.time <= 1500000) {
          console.log('本地获取session', res.data);
          page.setData({
            user: res.data
          });
          page.working = 0;
        } else {
          var times = 0;
          var getUser = setTimeout(function fn() {
            wx.login({
              success: function (res) {
                //登录服务器
                var code = res.code;
                console.log('登录数据', res);
                wx.getUserInfo({
                  success: function (res) {
                    console.log('userInfo', res);
                    var user = {};
                    user.name = res.userInfo.nickName;
                    user.img = res.userInfo.avatarUrl;

                    wx.request({
                      url: URL,
                      data: {
                        mod: 'api',
                        act: 'weapp',
                        opt: 'login',
                        code: code,
                        encryptedData: res.encryptedData,
                        iv: res.iv
                      },
                      success: function (res) {
                        if (res.data.code === 0) {
                          console.log('本地session失效,从网络获取', res);
                          clearInterval(getUser);
                          user.session = res.data.data.session;
                          user.time = Date.now();
                          page.setData({
                            user: user
                          });
                          wx.setStorage({
                            key: 'user',
                            data: user,
                          });
                          page.working = 0;
                        }
                      },
                      fail: function () {
                        if (times < 100) {
                          getUser = setTimeout(fn, 50);
                        }
                      },
                      complete: function () {
                        times++;
                      }
                    });
                  },
                  fail: function () {
                    // wx.showModal({
                    //   showCancel: false,
                    //   title: '温馨提示',
                    //   content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。'
                    // });
                  }
                })
              },
              fail: function () {
                getUser = setTimeout(fn, 50);
              }
            });
          }, 50);
        }
      },
      fail: function () {
        var getUser = setTimeout(function fn() {
          wx.login({
            success: function (res) {
              //登录服务器
              var code = res.code;
              console.log('登录数据', res);
              wx.getUserInfo({
                success: function (res) {
                  console.log('userInfo', res);
                  var user = {};
                  user.name = res.userInfo.nickName;
                  user.img = res.userInfo.avatarUrl;

                  wx.request({
                    url: URL,
                    data: {
                      mod: 'api',
                      act: 'weapp',
                      opt: 'login',
                      code: code,
                      encryptedData: res.encryptedData,
                      iv: res.iv
                    },
                    success: function (res) {
                      if (res.data.code === 0) {
                        console.log('本地没有session,从网络获取session', res);
                        clearInterval(getUser);
                        user.session = res.data.data.session;
                        user.time = Date.now();
                        page.setData({
                          user: user
                        });
                        wx.setStorage({
                          key: 'user',
                          data: user,
                        });
                        page.working = 0;
                      }
                    },
                    fail: function () {
                      if (times < 100) {
                        getUser = setTimeout(fn, 50);
                      }
                    },
                    complete: function () {
                      times++;
                    }
                  });
                },
                fail: function () {
                  // wx.showModal({
                  //   showCancel: false,
                  //   title: '温馨提示',
                  //   content: '您已拒绝微信（公开信息，地理位置）授权，请在关于借伞的设置中进入小程序授权并开启手机位置信息。'
                  // });
                }
              })
            },
            fail: function () {
              getUser = setTimeout(fn, 50);
            }
          });
        }, 50);
      }
    })
  }
}

module.exports = getUser;