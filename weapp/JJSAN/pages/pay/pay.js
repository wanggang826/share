var URL = require('../../utils/url.js');
var getUser = require('../../utils/getUser.js');
var holdUser = require('../../utils/holdUser.js');

Page({

  /**
   * 页面的初始数据
   */
  data: {
    show: [false, false, false, false, false, false, false, false, false],
    user: '',
    wallet: '',
    slot: '',
    interval: '',
    count: 0,
    pay: 0
  },

  /**
   * 生命周期函数--监听页面加载
   */
  onLoad: function (options) {
    var page = this;
    var show = page.data.show;

    wx.setNavigationBarTitle({
      title: '充值'
    });

    page.fromNet = 0;
    getUser(page);

    wx.getStorage({
      key: 'wallet',
      success: function (res) {
        console.log('wallet', res.data);
        var wallet = res.data;

        if (wallet.code === 0) {
          show[0] = true;
          page.setData({
            wallet: wallet,
            show: show
          });
        } else if (wallet.code === 2) {
          show[8] = true;
          page.setData({
            wallet: wallet,
            show: show
          });
        } else if (wallet.code === 3) {
          show[6] = true;
          page.setData({
            wallet: wallet,
            show: show
          });
        } else {
          wx.showToast({
            title: '通信异常',
          }, 2000);
          setTimeout(function () {
            wx.navigateBack({
              delta: 1
            })
          }, 2000)
        }
      }
    })
  },
  
  onHide: function () {
    var page = this;
    if (page.data.interval != '') {
      clearInterval(page.data.interval);
    }
  },

  onUnload: function () {
    var page = this;
    if (page.data.interval != '') {
      clearInterval(page.data.interval);
    }
  },

  pay: function (event) {
    var page = this;

    var formId = { form_id: event.detail.formId, count: 1 };

    var wallet = page.data.wallet;
    console.log('检查参数', wallet);

    var timeOut = page.data.timeOut;

    var callNow = !timeOut;

    timeOut = setTimeout(function () {
      timeOut = null;
      page.setData({
        timeOut: timeOut,
      })
    }, 10000);

    page.setData({
      timeOut: timeOut,
    });

    if (callNow) {
      page.setData({
        pay: 1
      });
      var times = 0;
      var pay = setTimeout(function fn() {
        wx.showNavigationBarLoading();
        wx.request({
          url: URL,
          data: {
            mod: 'api',
            act: 'weapp',
            opt: 'wxpay',
            sid: wallet.sid,
            tid: wallet.tid,
            session: page.data.user.session
          },
          success: function (res) {
            console.log('支付与网络通信成功的数据', res);
            var show = page.data.show;
            if (res.data.code === 0) {

              holdUser(page);

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

              var orderId = res.data.data.orderid;
              if (res.data.data.jsApiParameters) {
                var payInfo = res.data.data.jsApiParameters;
                console.log('payInfo', payInfo.package);
                wx.requestPayment({
                  timeStamp: payInfo.timeStamp,
                  nonceStr: payInfo.nonceStr,
                  package: payInfo.package,
                  signType: payInfo.signType,
                  paySign: payInfo.paySign,
                  success: function (res) {
                    console.log('支付的数据', JSON.stringify(res));

                    var formId = { form_id: payInfo.package.substring(10), count: 3 };

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
                        }
                      }
                    });

                    for (var i = 0; i < show.length; i++) {
                      if (i == 1) {
                        show[i] = true;
                        continue;
                      }
                      show[i] = false;
                    }
                    page.setData({
                      show: show
                    });
                    wx.setNavigationBarTitle({
                      title: '正在出伞'
                    });

                    var interval = setInterval(function () {
                      wx.request({
                        url: URL,
                        data: {
                          mod: 'api',
                          act: 'weapp',
                          opt: 'order_status',
                          order_id: orderId,
                          session: page.data.user.session
                        },
                        success: function (res) {
                          console.log('进行借伞流程的数据', res);
                          if (res.data.code == 5) {
                            if (!page.working) {
                              page.fromNet = 1;
                              getUser(page);
                            }
                          } else {
                            holdUser(page);

                            if (res.data.data.status == 1) {//支付成功
                              for (var i = 0; i < show.length; i++) {
                                if (i == 1) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              wx.setNavigationBarTitle({
                                title: '正在出伞'
                              })
                            } else if (res.data.data.status == 5) {//机器某槽位可以取伞
                              if (res.data.data.slot) {
                                for (var i = 0; i < show.length; i++) {
                                  if (i == 2) {
                                    show[i] = true;
                                    continue;
                                  }
                                  show[i] = false;
                                }
                                page.setData({
                                  slot: res.data.data.slot,
                                  show: show
                                });
                                wx.setNavigationBarTitle({
                                  title: '正在出伞'
                                })
                              }
                            } else if (res.data.data.status == 2) {//用户已经取走伞
                              for (var i = 0; i < show.length; i++) {
                                if (i == 3) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '街借伞'
                              });
                              wx.hideNavigationBarLoading();

                            } else if (res.data.data.status == 97 || res.data.data.status == 99) {//雨伞未取走
                              for (var i = 0; i < show.length; i++) {
                                if (i == 4) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '街借伞'
                              });
                              wx.hideNavigationBarLoading();

                            } else if (res.data.data.status == 70) {//没有合适的伞可用
                              for (var i = 0; i < show.length; i++) {
                                if (i == 6) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '街借伞'
                              });
                              wx.hideNavigationBarLoading();

                            } else if (res.data.data.status == 65) {//伞柜正在被其他人使用
                              for (var i = 0; i < show.length; i++) {
                                if (i == 7) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '街借伞'
                              });
                              wx.hideNavigationBarLoading();

                            } else if (res.data.data.status == 64) {//伞柜不在线
                              for (var i = 0; i < show.length; i++) {
                                if (i == 8) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '街借伞'
                              });
                              wx.hideNavigationBarLoading();

                            } else {
                              for (var i = 0; i < show.length; i++) {
                                if (i == 5) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                show: show
                              });
                              clearInterval(interval);
                              wx.setNavigationBarTitle({
                                title: '出伞失败'
                              });
                              wx.hideNavigationBarLoading();
                            }
                          }
                        },
                        fail: function () { }
                      });
                    }, 1000)

                    page.setData({
                      interval: interval
                    })
                  },
                  fail: function (res) {
                    wx.showToast({
                      title: '支付未能成功,请重新支付'
                    });
                    page.setData({
                      timeOut: null,
                      pay: 0
                    });
                    wx.hideNavigationBarLoading();
                  }
                })
              } else {
                //开始借伞
                if (orderId) {
                  for (var i = 0; i < show.length; i++) {
                    if (i == 1) {
                      show[i] = true;
                      continue;
                    }
                    show[i] = false;
                  }
                  page.setData({
                    show: show
                  });
                  wx.setNavigationBarTitle({
                    title: '正在出伞'
                  });

                  var interval = setInterval(function () {
                    wx.request({
                      url: URL,
                      data: {
                        mod: 'api',
                        act: 'weapp',
                        opt: 'order_status',
                        order_id: orderId,
                        session: page.data.user.session
                      },
                      success: function (res) {
                        console.log('进行借伞流程的数据', res);
                        if (res.data.code == 5) {
                          if (!page.working) {
                            page.fromNet = 1;
                            getUser(page);
                          }
                        } else {
                          holdUser(page);

                          if (res.data.data.status == 5 || res.data.data.status == 1) {//机器某槽位可以取伞
                            if (res.data.data.slot) {
                              for (var i = 0; i < show.length; i++) {
                                if (i == 2) {
                                  show[i] = true;
                                  continue;
                                }
                                show[i] = false;
                              }
                              page.setData({
                                slot: res.data.data.slot,
                                show: show
                              });
                              wx.setNavigationBarTitle({
                                title: '正在出伞'
                              })
                            }
                          } else if (res.data.data.status == 2) {//用户已经取走伞
                            for (var i = 0; i < show.length; i++) {
                              if (i == 3) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '街借伞'
                            });
                            wx.hideNavigationBarLoading();

                          } else if (res.data.data.status == 97 || res.data.data.status == 99) {//雨伞未取走
                            for (var i = 0; i < show.length; i++) {
                              if (i == 4) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '街借伞'
                            });
                            wx.hideNavigationBarLoading();

                          } else if (res.data.data.status == 70) {//没有合适的伞可用
                            for (var i = 0; i < show.length; i++) {
                              if (i == 6) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '街借伞'
                            });
                            wx.hideNavigationBarLoading();

                          } else if (res.data.data.status == 65) {//伞柜正在被其他人使用
                            for (var i = 0; i < show.length; i++) {
                              if (i == 7) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '街借伞'
                            });
                            wx.hideNavigationBarLoading();

                          } else if (res.data.data.status == 64) {//伞柜不在线
                            for (var i = 0; i < show.length; i++) {
                              if (i == 8) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '街借伞'
                            });
                            wx.hideNavigationBarLoading();

                          } else {
                            for (var i = 0; i < show.length; i++) {
                              if (i == 5) {
                                show[i] = true;
                                continue;
                              }
                              show[i] = false;
                            }
                            page.setData({
                              show: show
                            });
                            clearInterval(interval);
                            wx.setNavigationBarTitle({
                              title: '出伞失败'
                            });
                            wx.hideNavigationBarLoading();
                          }
                        }
                      }
                    });
                  }, 1000)

                  page.setData({
                    interval: interval
                  })
                }
              }

            } else if (res.data.code == 5) {
              if (!page.working) {
                page.fromNet = 1;
                getUser(page);
              }
              if (times < 100) {
                pay = setTimeout(fn, 50);
              }
            } else if (res.data.code == 3) {
              for (var i = 0; i < show.length; i++) {
                if (i == 6) {
                  show[i] = true;
                  continue;
                }
                show[i] = false;
              }
              page.setData({
                show: show
              });
              wx.hideNavigationBarLoading();
            } else {
              switch (res.data.code) {
                case 1:
                  wx.showToast({
                    title: '缺乏必要参数',
                    duration: 2000
                  });
                  break;

                case 2:
                  wx.showToast({
                    title: '机器仍在使用中',
                    duration: 2000
                  });
                  break;

                case 4:
                  wx.showToast({
                    title: '数据异常',
                    duration: 2000
                  });
                  break;

                case 6:
                  wx.showToast({
                    title: '不支持其他平台',
                    duration: 2000
                  });
                  break;
              }
              wx.hideNavigationBarLoading();
              setTimeout(function () {
                wx.navigateBack({
                  delta: 1
                })
              }, 3000);
            }
          },
          fail: function () {
            if (times < 100) {
              pay = setTimeout(fn, 50);
            }
          },
          complete: function () {
            times++;
          }
        });
      }, 50);
    }
  },

  onUnload: function () {
    var page = this;
    clearInterval(page.data.interval);
  },

  //继续借伞按钮
  retry: function (event) {
    var page = this;

    var formId = { form_id: event.detail.formId, count: 1 };

    wx.showNavigationBarLoading();

    wx.scanCode({
      onlyFromCamera: true,
      success: (res) => {
        console.log('二维码', res);
        var times = 0;
        var retry = setTimeout(function fn() {
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
              if (code == 5) {
                if (!page.working) {
                  page.fromNet = 1;
                  getUser(page);
                }
                if (time < 100) {
                  retry = setTimeout(fn, 50);
                }
              } else if (code == 555) {
                wx.showToast({
                  title: '二维码错误',
                  duration: 2000
                });
              } else {
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
                })

                var wallet;
                if (code === 0) {
                  holdUser(page);
                } else {
                  wallet = {};
                }
                wallet.code = res.data.code;
                console.log('存取的wallet', wallet);
                wx.setStorage({
                  key: 'wallet',
                  data: wallet,
                  success: function () {
                    wx.redirectTo({
                      url: '../pay/pay',
                    });
                  }
                });
              }
            },
            fail: function () {
              if (time < 100) {
                retry = setTimeout(fn, 50);
              }
            },
            complete: function () {
              times++;
            }
          });
        }, 50)
      }
    });
  },

  goToGuide: function (event) {
    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };
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
        if (res.data.code === 0) {
          holdUser(page);
        }
      }
    });

    wx.redirectTo({
      url: '../guide/guide',
    });
  },

  checkAgreement: function (event) {
    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };
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
        if (res.data.code === 0) {
          holdUser(page);
        }
      }
    })

    wx.navigateTo({
      url: '../agreement/agreement',
    })
  },

  call: function (event) {
    var page = this;
    var formId = { form_id: event.detail.formId, count: 1 };

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
        if (res.data.code === 0) {
          holdUser(page);
        }
      }
    })

    wx.makePhoneCall({
      phoneNumber: '4009008113',
      fail: function () {
        wx.showModal({
          title: '电话提示',
          content: '请拨打客服电话4009008113',
          showCancel: 'false'
        })
      }
    })
  }
})