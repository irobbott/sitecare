import { expect,test } from '@playwright/test'

test('client can open the sign-in form from the dashboard preview',async({page})=>{
  await page.goto('/')
  await expect(page.getByRole('heading',{name:/good morning/i})).toBeVisible()
  await expect(page.getByText('Website health')).toBeVisible()
  await page.getByRole('main').getByRole('button',{name:'Sign in'}).click()
  await expect(page.getByRole('heading',{name:'Sign in to SiteCare'})).toBeVisible()
  await expect(page.getByLabel('Email address')).toBeVisible()
})

test('sidebar navigation opens the websites workspace',async({page})=>{
  await page.goto('/')
  await page.getByRole('button',{name:'Websites',exact:true}).click()
  await expect(page.getByRole('heading',{name:'Your websites'})).toBeVisible()
  await expect(page.getByRole('heading',{name:'Sign in to open your workspace'})).toBeVisible()
})
