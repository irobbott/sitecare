import { expect,test } from '@playwright/test'

test('client can open the sign-in form from the dashboard preview',async({page})=>{
  await page.goto('/',{waitUntil:'commit'})
  await expect(page.getByRole('heading',{name:/good morning/i})).toBeVisible()
  await expect(page.getByText('Website health')).toBeVisible()
  await page.locator('.header-actions button.help-link').click()
  await expect(page.getByRole('heading',{name:'Sign in to SiteCare'})).toBeVisible()
  await expect(page.getByLabel('Email address')).toBeVisible()
})

test('sidebar navigation opens the websites workspace',async({page})=>{
  await page.goto('/',{waitUntil:'commit'})
  await page.getByRole('button',{name:'Websites',exact:true}).click()
  await expect(page.getByRole('heading',{name:'Your websites'})).toBeVisible()
  await expect(page.getByRole('heading',{name:'Sign in to open your workspace'})).toBeVisible()
})

test('password recovery explains the next step without exposing account status',async({page})=>{
  await page.goto('/forgot-password',{waitUntil:'commit'})
  await expect(page.getByRole('heading',{name:'Reset your password'})).toBeVisible()
  await expect(page.getByLabel('Email address')).toBeVisible()
  await expect(page.getByRole('button',{name:'Send reset link'})).toBeVisible()
})
