<?php if($this->versions):
?><div class="tl_version_panel"
><form action="<?php echo ampersand($this->Environment->request, true); ?>" id="tl_version" class="tl_form" method="post"
><div class="tl_formbody"
><input type="hidden" name="FORM_SUBMIT" value="tl_version"
/><select name="version" class="tl_select"
><?php while($this->versions->next()):
?><option value="<?php echo $this->versions->version; ?>"<?php if($this->versions->active): ?> selected="selected"<?php endif; ?>
><?php echo $GLOBALS['TL_LANG']['MSC']['version']; ?> <?php echo $this->versions->version; ?> (<?php echo $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $this->versions->tstamp); ?>) <?php echo $this->versions->username;
?></option
><?php endwhile;
?></select
><input type="submit" name="showVersion" id="showVersion" class="tl_submit" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['restore']); ?>"
/></div
></form
></div
><?php endif; ?>
<div id="tl_buttons">
<a href="<?php echo $this->getReferer(true); ?>" class="header_back" title="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['backBT']); ?>" accesskey="b" onclick="Backend.getScrollOffset();"><?php echo $GLOBALS['TL_LANG']['MSC']['backBT']; ?></a>
</div>

<h2 class="sub_headline"><?php echo $this->subHeadline; ?></h2>
<?php echo $this->getMessages(); ?>

<form class="tl_form tableextended" method="post"
  action="<?php echo ampersand($this->Environment->request, true); ?>"
  id="<?php echo $this->table; ?>"
  enctype="<?php echo $this->enctype; ?>"
  <?php if($this->onsubmit): ?> onsubmit="<?php echo $this->onsubmit; ?>"<?php endif; ?>>
<div class="tl_formbody_edit">
<input type="hidden" name="FORM_SUBMIT" value="<?php echo specialchars($this->table); ?>" />
<?php if($this->error): ?>
  <p class="tl_error"><?php echo $GLOBALS['TL_LANG']['ERR']['general']; ?></p>
  <script type="text/javascript">
  <!--//--><![CDATA[//><!--
	window.addEvent('domready', function() {
	    Backend.vScrollTo(($('<?php echo $this->table; ?>').getElement('label.error').getPosition().y - 20));
	});
  //--><!]]>
  </script>
<?php endif; ?>

<?php if($this->oldBE): foreach($this->fieldsets as $arrFieldset): ?>
<div class="<?php echo $arrFieldset['class']; ?> block">
  <?php echo $arrFieldset['palette']; ?>
</div>
<?php endforeach; else: foreach($this->fieldsets as $arrFieldset): if($arrFieldset['legend']): ?>
<fieldset id="pal_<?php echo specialchars($arrFieldset['legend']); ?>" class="<?php echo $arrFieldset['class']; ?> block">
<legend><?php echo $arrFieldset['label']; ?></legend>
  <?php echo $arrFieldset['palette']; ?>
</fieldset>
<?php else: ?>
<fieldset class="<?php echo $arrFieldset['class']; ?> block nolegend">
  <?php echo $arrFieldset['palette']; ?>
</fieldset>
<?php endif; endforeach; endif; ?>

</div>

<div class="tl_formbody_submit">

<div class="tl_submit_container">
<input type="submit" name="save" id="save" class="tl_submit" accesskey="s" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['save']); ?>" />
<input type="submit" name="saveNclose" id="saveNclose" class="tl_submit" accesskey="c" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['saveNclose']); ?>" />
<?php if($this->createButton): ?>
  <input type="submit" name="saveNcreate" id="saveNcreate" class="tl_submit" accesskey="n" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['saveNcreate']); ?>" />
<?php endif; if($this->editButton): ?>
  <input type="submit" name="saveNedit" id="saveNedit" class="tl_submit" accesskey="e" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['saveNedit']); ?>" />
<?php endif; if($this->backButton): ?>
  <input type="submit" name="saveNback" id="saveNback" class="tl_submit" accesskey="g" value="<?php echo specialchars($GLOBALS['TL_LANG']['MSC']['saveNback']); ?>" />
<?php endif; ?>
</div>

</div>
</form>