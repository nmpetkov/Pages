{gt text='Delete page' assign='templatetitle'}
{include file='admin/menu.tpl'}

<div class="z-admincontainer">
    <div class="z-adminpageicon">{img modname='core' src='editdelete.gif' set='icons/large' alt=$templatetitle}</div>

    <h2>{$templatetitle}</h2>

    <p class="z-warningmsg">{gt text='Do you really want to delete this page?'}</p>

    <form class="z-form" action="{modurl modname='Pages' type='admin' func='delete'}" method="post" enctype="application/x-www-form-urlencoded">
        <div>
            <input type="hidden" name="authid" value="{insert name='generateauthkey' module='Pages'}" />
            <input type="hidden" name="confirmation" value="1" />
            <input type="hidden" name="pageid" value="{$pageid|safetext}" />
            <div class="z-formbuttons">
                {button src='button_ok.gif' set='icons/small' __alt='Confirm deletion?' __title='Confirm deletion?'}
                <a href="{modurl modname=Pages type=admin func=view}">{img modname='core' src='button_cancel.gif' set='icons/small'  __alt='Cancel' __title='Cancel'}</a>
            </div>
        </div>
    </form>
</div>