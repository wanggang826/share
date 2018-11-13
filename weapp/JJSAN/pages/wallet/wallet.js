var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    user: '',
    items: [],
    page: 1,
    remind: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var page = this;
    var items = page.data.items;

    page.fromNet = 0;
    getUser(page);

    var number = page.data.page;
    wx.showNavigationBarLoading();
    var times = 0;

    var timeOut = setTimeout(function fn() {
      if (page.data.user.session) {
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'wallet_detail',
            session: page.data.user.session,
            page: number,
          },
          success: function (res) {

            if (res.data.code === 0) {
              holdUser(page);

              var temp = res.data.data;
              var len = temp.length;
              for (var i = 0; i < temp.length; i++) {
                switch (temp[i].type) {
                  case '1':
                    temp[i].name = '充值';
                    temp[i].amount = '+' + temp[i].amount;
                    break
                  case '2':
                    temp[i].name = '支付';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case '3':
                    temp[i].name = '提现';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case '4':
                    temp[i].name = '提现';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case '5':
                    temp[i].name = '退款';
                    temp[i].amount = '+' + temp[i].amount;
                    break
                  case 1:
                    temp[i].name = '充值';
                    temp[i].amount = '+' + temp[i].amount;
                    break
                  case 2:
                    temp[i].name = '支付';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case 3:
                    temp[i].name = '提现';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case 4:
                    temp[i].name = '提现';
                    temp[i].amount = '-' + temp[i].amount;
                    break
                  case 5:
                    temp[i].name = '退款';
                    temp[i].amount = '+' + temp[i].amount;
                    break
                }
                items.push(temp[i]);
              }

              number++;
              console.log('钱包明细', items);
              page.setData({
                page: number,
                items: items
              });
              wx.hideNavigationBarLoading();
            } else if (res.data.code === 2) {
              number++;
              page.setData({
                page: number,
                items: []
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
            if (times < 100) {
              timeOut = setTimeout(fn, 50);
            }
          },
          complete: function () {
            times++;
          }
        })
      } else {
        if (!page.working) {
          page.fromNet = 1;
          getUser(page);
        }
        if (times < 100) {
          timeOut = setTimeout(fn, 50);
        }
      }
    }, 50);
  },

  getMore: function () {
    console.log('滑到底部');
    var page = this;
    var items = page.data.items;
    var user = page.data.user;
    var number = page.data.page;
    wx.showNavigationBarLoading();

    wx.request({
      url: URL,
      data: {
        mod: 'api',
        act: 'weapp',
        opt: 'wallet_detail',
        session: user.session,
        page: number,
      },
      success: function (res) {
        console.log('钱包明细', res);

        if (res.data.code === 0) {
          holdUser(page);

          var temp = res.data.data
          var len = temp.length;
          for (var i = 0; i < temp.length; i++) {
            switch (temp[i].type) {
              case '1':
                temp[i].name = '充值';
                temp[i].amount = '+' + temp[i].amount;
                break
              case '2':
                temp[i].name = '支付';
                temp[i].amount = '-' + temp[i].amount;
                break
              case '3':
                temp[i].name = '提现';
                temp[i].amount = '-' + temp[i].amount;
                break
              case '4':
                temp[i].name = '提现';
                temp[i].amount = '-' + temp[i].amount;
                break
              case '5':
                temp[i].name = '退款';
                temp[i].amount = '+' + temp[i].amount;
                break
              case 1:
                temp[i].name = '充值';
                temp[i].amount = '+' + temp[i].amount;
                break
              case 2:
                temp[i].name = '支付';
                temp[i].amount = '-' + temp[i].amount;
                break
              case 3:
                temp[i].name = '提现';
                temp[i].amount = '-' + temp[i].amount;
                break
              case 4:
                temp[i].name = '提现';
                temp[i].amount = '-' + temp[i].amount;
                break
              case 5:
                temp[i].name = '退款';
                temp[i].amount = '+' + temp[i].amount;
                break
            }
            items.push(temp[i]);
          }

          number++;

          console.log(items);

          page.setData({
            page: number,
            items: items
          })
        } else if (res.data.code === 2) {
          if (page.data.remind === 0) {
            wx.showToast({
              title: '没有更多数据',
              duration: 2000
            });

            page.setData({
              remind: 1
            });
          }
        } else if (res.data.code === 5) {
          if (!page.working) {
            page.fromNet = 1;
            getUser(page);
          }
        }
        wx.hideNavigationBarLoading();
      },
      fail: function () {
        wx.hideNavigationBarLoading();
      }
    })
  }
})