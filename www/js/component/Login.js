// vim600: fdm=marker
///////////////////////////////////////////
// author     : Ben Kietzman
// begin      : 2022-11-11
// copyright  : kietzman.org
// email      : ben@kietzman.org
///////////////////////////////////////////
export default
{
  // {{{ controller()
  controller(id, nav)
  {
    // {{{ prep work
    let c = common;
    let s = c.store('Login',
    {
      b:
      {
        password: new Observable,
        userid: new Observable
      },
      c: c,
      processLogin: () =>
      {
        c.login.login.userid = s.b.userid.value;
        c.login.login.password = s.b.password.value;
        c.processLogin();
      },
      processLoginKey: () =>
      {
        if (window.event.keyCode == 13)
        {
          s.processLogin()
        }
      }
    });
    // }}}
    // {{{ main
    c.setMenu('Login', null);
    c.attachEvent('commonWsReady_' + c.application, (data) =>
    {
      c.processLogin();
    });
    c.processLogin();
    // }}}
  },
  // }}}
  // {{{ template
  template: `
    <br><br><br>
    {{#if c.login.message}}
    <div style="color:red;font-weight:bold;"><br><br>{{c.login.message}}<br><br></div>
    {{/if}}
    {{#if c.login.info}}
    <div style="color:orange;"><br><br>{{c.login.info}}<br><br></div>
    {{/if}}
    {{#if c.login.showForm}}
    <div class="row" style="width:50%;">
      <h3 class="page-header">{{c.login.login.title}}</h3>
      <div class="col-md-5" style="padding:10px;"><input class="form-control" type="text" c-model="userid" maxlength="20" c-keyup="processLoginKey()" placeholder="User" autofocus></div>
      <div class="col-md-5" style="padding:10px;"><input class="form-control" type="password" c-model="password" maxlength="64" c-keyup="processLoginKey()" placeholder="Password"></div>
      <div class="col-md-2" style="padding:10px;"><button class="btn btn-primary float-end" c-click="processLogin()">Login</button></div>
    </div>
    {{/if}}
  `
  // }}}
}
