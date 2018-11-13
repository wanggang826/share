var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: '',
    cash: '',
    count: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var page = this;
  },

  onShow: function () {
    var page = this;
    page.fromNet = 0;
    getUser(page);

    wx.showNavigationBarLoading();
    var times = 0;

    var timeOut = setTimeout(function fn() {
      if (page.data.user) {
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'user_info',
            session: page.data.user.session,
          },
          success: function (res) {
            console.log('用户的账户数据', res);
            if (res.data.code === 0) {
              holdUser(page);

              var cash = {};
              cash.usablemoney = res.data.data.usablemoney;
              cash.deposit = res.data.data.deposit;
              cash.refund = res.data.data.refund;
              cash.deposit_need = res.data.data.deposit_need;
              cash.unreturn = res.data.data.unreturn;
              cash.all = parseFloat(cash.usablemoney) + parseFloat(cash.deposit);
              page.setData({
                cash: cash
              });
              wx.hideNavigationBarLoading();
            } else {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
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
        });
      } else {
        if (times < 100) {
          timeOut = setTimeout(fn, 50);
        }
      }
    }, 50);
  },

  goDetails: function () {
    wx.navigateTo({
      url: '../wallet/wallet',
    })
  },

  takeCash: function (event) {
    var page = this;

    if (!(page.data.cash.usablemoney > 0)) {
      wx.showToast({
        title: '余额不足'
      });
      return;
    }

    var formId = { form_id: event.detail.formId, count: 1 };

    var timeOut = page.data.timeOut;

    var callNow = !timeOut;

    timeOut = setTimeout(function () {
      timeOut = null;
      page.setData({
        timeOut: timeOut
      });
    }, 4000);

    page.setData({
      timeOut: timeOut
    });

    if (callNow) {
      wx.showNavigationBarLoading();
      var times = 0;
      var call = setTimeout(function () {
        if (page.data.user) {
          wx.request({
            url: URL,
            data: {
              mod: 'api',
              act: 'weapp',
              opt: 'refund',
              session: page.data.user.session
            },
            success: function (res) {
              console.log("提现数据:", res);
              if (res.data.code != 2 && res.data.code != 5) {
                switch (res.data.code) {
                  case 0:
                    wx.showToast({
                      title: '到账情况见钱包'
                    });
                    holdUser(page);

                    break;
                  case 3:
                    wx.showToast({
                      title: '余额不足'
                    });
                    holdUser(page);
                    break;
                }
                wx.request({
                  url: URL,
                  data: {
                    mod: 'api',
                    act: 'weapp',
                    opt: 'user_info',
                    session: page.data.user.session,
                  },
                  success: function (res) {
                    console.log('用户的账户数据', res);
                    if (res.data.code === 0) {
                      holdUser(page);

                      var cash = {};
                      cash.usablemoney = res.data.data.usablemoney;
                      cash.deposit = res.data.data.deposit;
                      cash.deposit_need = res.data.data.deposit_need;
                      cash.unreturn = res.data.data.unreturn;
                      cash.all = parseFloat(cash.usablemoney) + parseFloat(cash.deposit);
                      page.setData({
                        cash: cash
                      });
                      wx.hideNavigationBarLoading();
                    }
                  }
                });

                wx.request({
                  url: URL,
                  data: {
                    mod: 'api',
                    act: 'weapp',
                    opt: 'form_id',
                    form_id: formId,
                    session: page.data.user.session
                  },
                  success: function (res) { }
                });
              } else {
                if (!page.working) {
                  page.fromNet = 1;
                  getUser(page);
                }
                if (times < 100) {
                  call = setTimeout(fn, 50);
                }
              }
            },
            fail: function () {
              if (times < 100) {
                call = setTimeout(fn, 50);
              }
            },
            complete: function () {
              times++;
            }
          })
        } else {
          if (times < 100) {
            call = setTimeout(fn, 50);
          }
        }
      }, 50);
    }
  },

  call: function () {
    wx.makePhoneCall({
      phoneNumber: '4009008113',
    })
  }
})