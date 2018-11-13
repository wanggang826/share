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
    count: 0,
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {

  },

  onShow: function () {
    console.log('执行一次onshow');
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
              cash.deposit_need = res.data.data.deposit_need;
              cash.unreturn = res.data.data.unreturn;
              cash.all = parseFloat(cash.usablemoney) + parseFloat(cash.deposit);

              page.setData({
                cash: cash
              });
              wx.hideNavigationBarLoading();
            } else if (res.data.code == 5) {
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
            if(times<100){
              timeOut = setTimeout(fn, 50);
            }
          },
          complete: function () {
            times++;
          }
        });
      }
    }, 50)
  },

  goWallet: function (event) {

    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };

    console.log('formId', formId);

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'form_id',
        form_id: formId,
        session: page.data.user.session
      },
      success: function (res) {
        if (res.data.code == 5) {
          if (!page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        } else if (res.data.code === 0) {
          holdUser(page);
        }
      }
    });

    wx.navigateTo({
      url: '../takecash/takecash'
    });
  },

  goHistory: function (event) {

    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };
    console.log('formId', formId);

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'form_id',
        form_id: formId,
        session: page.data.user.session
      },
      success: function (res) {
        if (res.data.code == 5) {
          if (!page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        } else if (res.data.code === 0) {
          holdUser(page);
        }
      }
    });

    wx.navigateTo({
      url: '../history/history'
    });
  },

  goAgreement: function (event) {
    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };

    console.log('formId', formId);

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'form_id',
        form_id: formId,
        session: page.data.user.session
      },
      success: function (res) {
        if (res.data.code == 5) {
          if (!page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        } else if (res.data.code === 0) {
          holdUser(page);
        }
      }
    });

    wx.navigateTo({
      url: '../agreement/agreement'
    });
  },

  goHelp: function (event) {

    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };

    console.log('formId', formId);

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'form_id',
        form_id: formId,
        session: page.data.user.session
      },
      success: function (res) {
        if (res.data.code == 5) {
          if (!page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        } else if (res.data.code === 0) {
          holdUser(page);
        }
      }
    });

    wx.navigateTo({
      url: '../help/help'
    });
  }
})